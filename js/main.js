let products = [];
let allCategories = [];
let cart = loadCart();
let csrfToken = '';

let currentFilter = 'all';
let searchTerm = '';
let currentPage = 1;
let currentProduct = null;
const PAGE_SIZE = 12;

// Config: número de WhatsApp de la tienda (código + número, sin "+" ni espacios).
// Se define en js/config.local.js (NO versionado en git; ver js/config.local.example.js).
// Si falta, se usa un placeholder y se muestra una advertencia en consola.
const STORE_WHATSAPP =
  typeof window !== 'undefined' && typeof window.SWEETCUT_WHATSAPP === 'string' && window.SWEETCUT_WHATSAPP
    ? window.SWEETCUT_WHATSAPP
    : '56900000000';
if (STORE_WHATSAPP === '56900000000') {
  console.warn('SweetCut: configura tu número de WhatsApp en js/config.local.js (ver js/config.local.example.js).');
}

// Regiones de Chile (ventas locales, pedido simple sin boleta/RUT)
const REGIONS = [
  'Arica y Parinacota', 'Tarapacá', 'Antofagasta', 'Atacama', 'Coquimbo',
  'Valparaíso', 'Metropolitana de Santiago', "O'Higgins", 'Maule', 'Ñuble',
  'Biobío', 'Araucanía', 'Los Ríos', 'Los Lagos', 'Aysén', 'Magallanes'
];

function formatCLP(value) {
  return '$' + Number(value).toLocaleString('es-CL');
}

// Escapa texto de la BD antes de inyectarlo en el HTML (previene XSS)
function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  })[c]);
}

// ---------- FILTROS DE CATEGORÍA (dinámicos desde la BD) ----------

function buildFilterBar() {
  const bar = document.getElementById('filterBar');
  const buttons = [
    `<button class="filter-btn ${currentFilter === 'all' ? 'active' : ''}" data-cat="all">Todos</button>`,
    ...allCategories.map(c => {
      const label = c.emoji ? `${c.emoji} ${c.label}` : c.label;
      return `<button class="filter-btn ${currentFilter === c.name ? 'active' : ''}" data-cat="${escapeHtml(c.name)}">${escapeHtml(label)} (${c.count})</button>`;
    })
  ];
  bar.innerHTML = buttons.join('');
}

function setFilterDirect(cat) {
  currentFilter = cat;
  currentPage = 1;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.toggle('active', b.dataset.cat === cat));
  renderProducts();
}

function setFilter(cat, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  currentFilter = cat;
  currentPage = 1;
  renderProducts();
}

// Usado por las tarjetas de la sección "Categorías"
function filterProducts(cat) {
  setFilterDirect(cat);
  document.getElementById('productos').scrollIntoView({ behavior: 'smooth' });
}

// ---------- BÚSQUEDA Y PAGINACIÓN ----------

function getFiltered() {
  let list = products;
  if (currentFilter !== 'all') {
    list = list.filter(p => p.cat === currentFilter);
  }
  if (searchTerm) {
    const t = searchTerm.toLowerCase();
    list = list.filter(p => (p.name + ' ' + (p.desc || '')).toLowerCase().includes(t));
  }
  return list;
}

function renderPagination(totalPages) {
  const container = document.getElementById('pagination');
  if (totalPages <= 1) {
    container.innerHTML = '';
    return;
  }

  const pages = [];
  pages.push(`<button class="page-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>&laquo; Anterior</button>`);
  for (let i = 1; i <= totalPages; i++) {
    pages.push(`<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`);
  }
  pages.push(`<button class="page-btn" onclick="goPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Siguiente &raquo;</button>`);
  container.innerHTML = pages.join('');
}

function goPage(page) {
  const list = getFiltered();
  const totalPages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
  if (page < 1 || page > totalPages) return;
  currentPage = page;
  renderProducts();
}

// ---------- RENDER ----------

function productCard(p) {
  const soldOut = p.stock !== null && p.stock <= 0;
  const badge = soldOut ? '<span class="stock-badge">Agotado</span>' : '';
  return `
    <div class="product-card" data-cat="${escapeHtml(p.cat)}" onclick="openProduct(${p.id})">
      <div class="product-image">
        ${badge}
        ${p.imageThumb ? `<img src="${escapeHtml(p.imageThumb)}" alt="${escapeHtml(p.name)}" class="product-img" loading="lazy" decoding="async">` : (p.image ? `<img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}" class="product-img" loading="lazy" decoding="async">` : `<span class="product-emoji">${escapeHtml(p.emoji)}</span>`)}
      </div>
      <div class="product-info">
        <div class="product-category">${escapeHtml(p.cat)}</div>
        <h3>${escapeHtml(p.name)}</h3>
        <p class="product-desc">${escapeHtml(p.desc)}</p>
        <div class="product-footer">
          <span class="product-price">${formatCLP(p.price)}${p.oldPrice ? `<span class="old">${formatCLP(p.oldPrice)}</span>` : ''}</span>
          <button class="add-cart-btn" onclick="event.stopPropagation(); addToCart(${p.id}, this)" ${soldOut ? 'disabled' : ''} aria-label="${soldOut ? 'Agotado' : 'Agregar al pedido'}">+</button>
        </div>
      </div>
    </div>
  `;
}

function renderProducts() {
  const grid = document.getElementById('productGrid');
  const list = getFiltered();

  if (list.length === 0) {
    grid.innerHTML = '<p class="load-error">No se encontraron productos. Prueba con otra búsqueda o categoría.</p>';
    renderPagination(1);
    return;
  }

  const totalPages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
  if (currentPage > totalPages) currentPage = totalPages;
  const pageList = list.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

  grid.innerHTML = pageList.map(productCard).join('');
  renderPagination(totalPages);
}

// ---------- CARRITO / PEDIDO (sin pasarela; se confirma por WhatsApp) ----------

// Aviso breve tipo toast (mensajes de stock / carrito)
let toastTimer = null;
function toast(msg) {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
}

// Límite de unidades de un producto (NULL = ilimitado)
function itemLimit(p) {
  return p.stock !== null ? p.stock : 99;
}

function loadCart() {
  try {
    const raw = JSON.parse(localStorage.getItem('sweetcut_cart') || '[]');
    return Array.isArray(raw) ? raw : [];
  } catch (e) {
    return [];
  }
}

function saveCart() {
  localStorage.setItem('sweetcut_cart', JSON.stringify(cart));
}

function addToCart(id, btn) {
  const p = products.find(x => x.id === id);
  if (!p) return;
  const limit = itemLimit(p);
  const existing = cart.find(i => i.id === id);
  const current = existing ? existing.qty : 0;
  if (current >= limit) {
    toast(p.stock === 0 ? 'Este producto está agotado.' : `Solo quedan ${p.stock} unidades.`);
    return;
  }
  if (existing) {
    existing.stock = p.stock;
    existing.qty++;
  } else {
    cart.push({
      id: p.id,
      name: p.name,
      price: p.price,
      image: p.imageThumb || p.image || '',
      emoji: p.emoji || '',
      stock: p.stock,
      qty: 1
    });
  }
  saveCart();
  renderCart();
  if (btn) {
    btn.style.transform = 'scale(1.3)';
    setTimeout(() => btn.style.transform = '', 200);
  }
}

function toggleCart(open) {
  document.getElementById('cartOverlay').classList.toggle('show', open);
  document.getElementById('cartDrawer').classList.toggle('open', open);
  document.body.style.overflow = open ? 'hidden' : '';
  if (open) renderCart();
}

function changeQty(i, delta) {
  const it = cart[i];
  if (!it) return;
  if (delta > 0 && it.stock !== null && it.qty + delta > it.stock) {
    toast(`Solo quedan ${it.stock} unidades.`);
    return;
  }
  it.qty += delta;
  if (it.qty <= 0) {
    cart.splice(i, 1);
  } else if (it.qty > 99) {
    it.qty = 99;
  }
  saveCart();
  renderCart();
}

function removeFromCart(i) {
  cart.splice(i, 1);
  saveCart();
  renderCart();
}

function renderCart() {
  const container = document.getElementById('cartItems');
  const countEl = document.getElementById('cartCount');
  const totalEl = document.getElementById('cartTotal');
  const checkoutBtn = document.getElementById('cartCheckoutBtn');
  const count = cart.reduce((s, i) => s + i.qty, 0);
  const total = cart.reduce((s, i) => s + i.price * i.qty, 0);

  countEl.textContent = count;
  totalEl.textContent = formatCLP(total);
  checkoutBtn.disabled = count === 0;

  if (cart.length === 0) {
    container.innerHTML = '<p class="cart-empty">Tu pedido está vacío.<br>Agrega cortadores desde el catálogo.</p>';
    return;
  }

  container.innerHTML = cart.map((it, i) => {
    const atLimit = it.stock !== null && it.qty >= it.stock;
    return `
    <div class="cart-item">
      ${it.image
        ? `<img src="${escapeHtml(it.image)}" alt="" class="cart-item-img" loading="lazy" decoding="async">`
        : `<span class="cart-item-emoji">${escapeHtml(it.emoji) || '🍪'}</span>`}
      <div class="cart-item-info">
        <strong>${escapeHtml(it.name)}</strong>
        <small>${formatCLP(it.price)} CLP</small>
      </div>
      <div class="cart-qty">
        <button type="button" onclick="changeQty(${i}, -1)" aria-label="Quitar uno">−</button>
        <span>${it.qty}</span>
        <button type="button" onclick="changeQty(${i}, 1)" aria-label="Agregar uno" ${atLimit ? 'disabled' : ''}>+</button>
      </div>
      <button type="button" class="cart-remove" onclick="removeFromCart(${i})" aria-label="Quitar del pedido">&times;</button>
    </div>`;
  }).join('');
}

// ---------- CHECKOUT (sin pago en línea) ----------

function populateRegions() {
  const sel = document.getElementById('regionSelect');
  if (sel.dataset.ready) return;
  sel.innerHTML = '<option value="">Selecciona...</option>' +
    REGIONS.map(r => `<option value="${escapeHtml(r)}">${escapeHtml(r)}</option>`).join('');
  sel.dataset.ready = '1';
}

function updateAddressVisibility() {
  const checked = document.querySelector('input[name="delivery_type"]:checked');
  const isDelivery = checked && checked.value === 'delivery';
  const field = document.getElementById('addressField');
  const input = field.querySelector('input');
  field.style.display = isDelivery ? '' : 'none';
  input.required = isDelivery;
}

function openCheckout() {
  if (cart.length === 0) return;
  toggleCart(false);
  populateRegions();
  document.getElementById('checkoutForm').reset();
  document.getElementById('checkoutMsg').textContent = '';
  document.getElementById('checkoutFormWrap').hidden = false;
  document.getElementById('checkoutOk').hidden = true;
  document.getElementById('checkoutModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  updateAddressVisibility();
}

function closeCheckout() {
  document.getElementById('checkoutModal').classList.remove('open');
  document.body.style.overflow = '';
}

async function submitOrder(e) {
  e.preventDefault();
  const form = document.getElementById('checkoutForm');
  const msg = document.getElementById('checkoutMsg');
  const btn = document.getElementById('checkoutSubmit');
  if (!form.reportValidity()) return;
  if (cart.length === 0) {
    msg.textContent = 'Tu pedido está vacío.';
    return;
  }

  const f = new FormData(form);
  const payload = {
    customer_name: f.get('name'),
    customer_phone: f.get('phone'),
    customer_email: f.get('email') || '',
    region: f.get('region'),
    commune: f.get('commune'),
    address: f.get('delivery_type') === 'delivery' ? f.get('address') : '',
    delivery_type: f.get('delivery_type'),
    payment_method: f.get('payment_method'),
    notes: f.get('notes') || '',
    items: cart.map(i => ({ product_id: i.id, qty: i.qty }))
  };

  msg.textContent = '';
  btn.disabled = true;
  btn.textContent = 'Guardando...';

  try {
    const res = await fetch('api/orders.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.error || 'No se pudo registrar tu pedido.');
    showOrderSuccess(json, payload);
  } catch (err) {
    msg.textContent = err.message;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Confirmar pedido';
  }
}

function paymentLabel(key) {
  return key === 'efectivo' ? 'Efectivo'
    : key === 'transferencia' ? 'Transferencia bancaria'
    : 'Contra entrega';
}

function showOrderSuccess(order, payload) {
  const lines = cart.map(i => `• ${i.qty}x ${i.name} = ${formatCLP(i.price * i.qty)}`);
  const waText =
    '¡Hola! Quiero confirmar mi pedido ' + order.order_no + '\n\n' +
    lines.join('\n') +
    '\n\nTotal: ' + formatCLP(order.total) +
    '\nEntrega: ' + (payload.delivery_type === 'delivery'
      ? 'Despacho a ' + payload.address + ', ' + payload.commune + ', ' + payload.region
      : 'Retiro en local (' + payload.commune + ', ' + payload.region + ')') +
    '\nPago: ' + paymentLabel(payload.payment_method) +
    (payload.notes ? '\nNotas: ' + payload.notes : '');

  document.getElementById('checkoutOkText').innerHTML =
    'Tu pedido <strong>' + escapeHtml(order.order_no) + '</strong> por <strong>' + formatCLP(order.total) +
    '</strong> quedó registrado. Envíalo por WhatsApp para confirmar con la tienda.';
  document.getElementById('checkoutWaLink').href =
    'https://wa.me/' + STORE_WHATSAPP + '?text=' + encodeURIComponent(waText);
  document.getElementById('checkoutFormWrap').hidden = true;
  document.getElementById('checkoutOk').hidden = false;
}

function finishOrder() {
  cart = [];
  saveCart();
  renderCart();
  closeCheckout();
  document.getElementById('checkoutFormWrap').hidden = false;
  document.getElementById('checkoutOk').hidden = true;
  document.getElementById('checkoutForm').reset();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ---------- MODAL DE DETALLE ----------

function openProduct(id) {
  const p = products.find(x => x.id === id);
  if (!p) return;

  currentProduct = p;
  const soldOut = p.stock !== null && p.stock <= 0;
  const addBtn = document.getElementById('modalAdd');
  addBtn.disabled = soldOut;
  addBtn.textContent = soldOut ? 'Agotado' : 'Agregar al pedido';

  document.getElementById('modalImage').innerHTML = (p.imageThumb || p.image)
    ? `<img src="${escapeHtml(p.imageThumb || p.image)}" alt="${escapeHtml(p.name)}">`
    : `<span class="modal-emoji">${escapeHtml(p.emoji)}</span>`;
  document.getElementById('modalCat').textContent = p.cat;
  document.getElementById('modalName').textContent = p.name;
  document.getElementById('modalDesc').textContent = p.desc;
  document.getElementById('modalPrice').innerHTML =
    `<span class="price-current">${formatCLP(p.price)}</span>` +
    (p.oldPrice ? `<span class="old">${formatCLP(p.oldPrice)}</span>` : '');

  document.getElementById('productModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('productModal').classList.remove('open');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  closeModal();
  closeCheckout();
  toggleCart(false);
});

// Botón del modal usa el producto abierto (agrega al pedido y abre el drawer)
document.addEventListener('click', e => {
  if (e.target.id === 'modalAdd' && currentProduct) {
    addToCart(currentProduct.id);
    closeModal();
    toggleCart(true);
  }
});

// ---------- SCRIPTS ----------

// Scroll nav effect
window.addEventListener('scroll', () => {
  document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50);
  document.getElementById('scrollTop').classList.toggle('visible', window.scrollY > 400);
});

// Fade-up on scroll
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));

// Buscador (con debounce para no re-renderizar en cada tecla)
let searchTimer = null;
document.getElementById('searchInput').addEventListener('input', e => {
  searchTerm = e.target.value.trim();
  currentPage = 1;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(renderProducts, 180);
});

// Botones de filtro dinámicos (delegación de eventos)
document.getElementById('filterBar').addEventListener('click', e => {
  const btn = e.target.closest('.filter-btn');
  if (btn) setFilter(btn.dataset.cat, btn);
});

// Checkout
document.getElementById('checkoutForm').addEventListener('submit', submitOrder);
document.querySelectorAll('input[name="delivery_type"]').forEach(r => r.addEventListener('change', updateAddressVisibility));

// ---------- CARGA DE DATOS (un solo endpoint con productos + categorías) ----------

function loadCatalog() {
  fetch('api/products.php')
    .then(res => {
      if (!res.ok) throw new Error('Error al obtener los productos');
      return res.json();
    })
    .then(data => {
      products = Array.isArray(data.products) ? data.products : [];
      allCategories = Array.isArray(data.categories) ? data.categories : [];
      buildFilterBar();
      updateCategoryCards();
      renderProducts();
    })
    .catch(err => {
      console.error('No se pudieron cargar los productos:', err);
      const grid = document.getElementById('productGrid');
      grid.innerHTML = '<p class="load-error">No se pudieron cargar los productos. Verifica que Apache y MySQL estén activos y que la base de datos exista.</p>';
    });
}

// Conteo dinámico en las tarjetas de la sección "Categorías"
function updateCategoryCards() {
  const counts = {};
  products.forEach(p => { counts[p.cat] = (counts[p.cat] || 0) + 1; });
  document.querySelectorAll('.cat-card').forEach(card => {
    const name = card.dataset.name;
    const count = counts[name] || 0;
    const span = card.querySelector('.cat-count');
    if (span) span.textContent = `${count} ${count === 1 ? 'diseño' : 'diseños'}`;
  });
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  loadCatalog();
  loadCsrf();
  renderCart();
});

// Token CSRF para el pedido público (api/csrf.php)
async function loadCsrf() {
  try {
    const res = await fetch('api/csrf.php');
    if (!res.ok) return;
    const json = await res.json();
    csrfToken = json.token || '';
  } catch (e) {
    csrfToken = '';
  }
}