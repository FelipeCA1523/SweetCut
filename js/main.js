let products = [];
let allCategories = [];
let cart = [];

let currentFilter = 'all';
let searchTerm = '';
let currentPage = 1;
let currentProduct = null;
const PAGE_SIZE = 12;

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
    `<button class="filter-btn ${currentFilter === 'all' ? 'active' : ''}" data-cat="all" onclick="setFilter('all', this)">Todos</button>`,
    ...allCategories.map(c => {
      const label = c.emoji ? `${c.emoji} ${c.label}` : c.label;
      return `<button class="filter-btn ${currentFilter === c.name ? 'active' : ''}" data-cat="${escapeHtml(c.name)}" onclick="setFilter('${escapeHtml(c.name)}', this)">${escapeHtml(label)} (${c.count})</button>`;
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
  return `
    <div class="product-card" data-cat="${escapeHtml(p.cat)}" onclick="openProduct(${p.id})">
      <div class="product-image">
        ${p.imageThumb ? `<img src="${escapeHtml(p.imageThumb)}" alt="${escapeHtml(p.name)}" class="product-img" loading="lazy">` : (p.image ? `<img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}" class="product-img" loading="lazy">` : `<span class="product-emoji">${escapeHtml(p.emoji)}</span>`)}
      </div>
      <div class="product-info">
        <div class="product-category">${escapeHtml(p.cat)}</div>
        <h3>${escapeHtml(p.name)}</h3>
        <p class="product-desc">${escapeHtml(p.desc)}</p>
        <div class="product-footer">
          <span class="product-price">${formatCLP(p.price)}${p.oldPrice ? `<span class="old">${formatCLP(p.oldPrice)}</span>` : ''}</span>
          <button class="add-cart-btn" onclick="event.stopPropagation(); addToCart(${p.id}, '${escapeHtml(p.name).replace(/'/g, "\\'")}', ${p.price})">+</button>
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

// ---------- CARRITO (por ahora simple, vía alert) ----------

function addToCart(id, name, price) {
  cart.push({ id, name, price });
  document.getElementById('cartCount').textContent = cart.length;
  const btn = event.target;
  btn.style.transform = 'scale(1.3)';
  setTimeout(() => btn.style.transform = '', 200);
}

function openCart() {
  if (cart.length === 0) { alert('Tu carrito está vacío'); return; }
  const total = cart.reduce((s, i) => s + i.price, 0);
  const items = cart.map(i => `${i.name} - ${formatCLP(i.price)}`).join('\n');
  alert(`Tu carrito:\n\n${items}\n\nTotal: ${formatCLP(total)}`);
}

// ---------- MODAL DE DETALLE ----------

function openProduct(id) {
  const p = products.find(x => x.id === id);
  if (!p) return;

  currentProduct = p;

  document.getElementById('modalImage').innerHTML = p.image
    ? `<img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}">`
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
  if (e.key === 'Escape') closeModal();
});

// Botón del modal usa el producto abierto
document.addEventListener('click', e => {
  if (e.target.id === 'modalAdd' && currentProduct) {
    cart.push({ id: currentProduct.id, name: currentProduct.name, price: currentProduct.price });
    document.getElementById('cartCount').textContent = cart.length;
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

// Buscador
document.getElementById('searchInput').addEventListener('input', e => {
  searchTerm = e.target.value.trim();
  currentPage = 1;
  renderProducts();
});

// ---------- CARGA DE DATOS ----------

function loadProducts() {
  fetch('api/products.php')
    .then(res => {
      if (!res.ok) throw new Error('Error al obtener los productos');
      return res.json();
    })
    .then(data => {
      products = data;
      updateCategoryCards();
      renderProducts();
    })
    .catch(err => {
      console.error('No se pudieron cargar los productos:', err);
      const grid = document.getElementById('productGrid');
      grid.innerHTML = '<p class="load-error">No se pudieron cargar los productos. Verifica que Apache y MySQL estén activos y que la base de datos exista.</p>';
    });
}

function loadCategories() {
  fetch('api/categories.php')
    .then(res => {
      if (!res.ok) throw new Error('Error al obtener las categorías');
      return res.json();
    })
    .then(data => {
      allCategories = data;
      buildFilterBar();
    })
    .catch(err => {
      console.error('No se pudieron cargar las categorías:', err);
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
  loadCategories();
  loadProducts();
});