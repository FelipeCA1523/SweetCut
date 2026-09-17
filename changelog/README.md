# Registro de cambios de sesiones

Este directorio guarda el seguimiento de lo que se hace con el asistente en cada sesión de trabajo sobre el proyecto **SweetCut**.

## Reglas

1. Un archivo por sesión, con nombre `YYYY-MM-DD-sesion-NN-descripcion.md`.
2. La sesión se registra **después** de terminar el trabajo, no antes.
3. Si la sesión toca migraciones SQL, se crea (o indica) el archivo `db/update-X.Y.sql` correspondiente.
4. El índice de este README se actualiza al agregar un archivo nuevo.

## Índice

| Fecha | Sesión | Alcance |
|-------|--------|---------|
| 2026-09-11 | [Sesión 05 — Preparación repo público](2026-09-11-sesion-05-repo-publico.md) | Config local de WhatsApp (no exponer el número real) + licencia MIT |
| 2026-09-11 | [Sesión 04 — Stock y dashboard](2026-09-11-sesion-04-stock-y-dashboard.md) | Stock/inventario (valida y descuenta en servidor con FOR UPDATE, badge Agotado, campo y columna en admin) y dashboard de ventas en el panel |
| 2026-09-11 | [Sesión 03 — Revisión integral](2026-09-11-sesion-03-revision-integral.md) | Revisión total: seguridad (honeypot, límite por IP, cabeceras), rendimiento (endpoint unificado, índices, debounce) y visual/UX |
| 2026-09-11 | [Sesión 02 — Pedidos por WhatsApp](2026-09-11-sesion-02-pedidos-por-whatsapp.md) | Pedidos sin pasarela de pago ni boleta + confirmación por WhatsApp (carrito, checkout, admin de pedidos) |
| 2026-09-11 | [Sesión 01 — Auditoría inicial](2026-09-11-sesion-01-auditoria-inicial.md) | Auditoría completa del proyecto (seguridad, diseño, rendimiento) y correcciones |