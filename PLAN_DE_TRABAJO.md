# Plan de trabajo — Mi Tienda v2 (producto vendible para PyMEs)

> Objetivo: convertir el sistema de inventario/compras/ventas en un producto comercial: **seguro**, **completo** (inventario, utilidades, caja, cuentas) y **atractivo** (landing, login, panel). Facturación electrónica SUNAT queda fuera de alcance por decisión del dueño.
>
> Leyenda: ✅ hecho · 🔄 en curso · ⬜ pendiente · 💡 idea futura

## Fase 0 — Diagnóstico (11-sep-2026) ✅

Hallazgos críticos del código heredado:

| # | Problema | Riesgo |
|---|----------|--------|
| 1 | 11 de 14 endpoints `ajax/` no verificaban sesión ni permisos | Cualquiera podía listar/editar datos sin login |
| 2 | SQL interpolado en todos los modelos; `$_GET['id']` sin sanear en `listarDetalle`, `permisos`, reportes | Inyección SQL |
| 3 | Contraseñas SHA256 sin sal; sin límite de intentos | Fuerza bruta / rainbow tables |
| 4 | Sin CSRF, sin cabeceras de seguridad, cookies de sesión sin HttpOnly/SameSite | CSRF, clickjacking, robo de sesión |
| 5 | Anular venta/ingreso NO revertía stock | Inventario incorrecto |
| 6 | Precio de venta dependía del último ingreso (artículo sin compras = precio 0) | Ventas a S/ 0 |
| 7 | Totales de venta/compra confiados al cliente | Manipulación de montos |
| 8 | Subida de imágenes validada solo por MIME del navegador | Subida de PHP/webshell |
| 9 | Restaurar backup ejecutaba SQL arbitrario sin respaldo previo | Pérdida de datos |
| 10 | Eliminar cliente/proveedor era DELETE físico (fallaba con FK) | Error 500 / pérdida de historial |
| 11 | Sin auditoría, sin logs | Imposible rastrear acciones |
| 12 | Landing inexistente (index redirigía a login); UI heredada de tutorial | No vendible |

## Fase 1 — Seguridad (prioridad máxima) ✅ (salvo 2FA/CSP)

- ✅ `config/global.php` con `config/local.php` opcional (credenciales fuera de git), `APP_ENV`, timezone, `appLog()`
- ✅ `config/Conexion.php`: helpers de consultas preparadas (`dbQuery/dbRow/dbAll/dbExec/dbInsert/dbTransaccion`), errores a `logs/app.log`
- ✅ `config/seguridad.php`: sesión endurecida (HttpOnly, SameSite, strict mode, expiración por inactividad, regeneración), cabeceras HTTP, CSRF (header `X-CSRF-Token`), `requiereLogin()/requierePermiso()`, bloqueo por intentos (`intento_login`), bcrypt con migración transparente desde SHA256, auditoría, validación real de imágenes (finfo + getimagesize + tamaño)
- ✅ Migración `20260911_seguridad_inventario.sql` + runner `scripts/migrar.php`
- ✅ Refactor de los 14 modelos y 14 controladores a consultas preparadas + permisos + auditoría
- ✅ Login nuevo (`vistas/login.php`) con CSRF, mensajes de bloqueo, "recordarme" solo del usuario
- ✅ `.htaccess`: bloquear acceso directo a `config/`, `modelos/`, `migrations/`, `logs/`, `*.sql`; impedir ejecución PHP en `files/`
- ✅ Reportes (`reportes/*.php`): sanear `id`, exigir permiso
- ✅ Cambio de contraseña desde "Mi perfil"
- 💡 2FA por correo/TOTP · 💡 CSP estricta (requiere mover JS inline)

## Fase 2 — Funcionalidad de inventario completo ✅ (pendiente: etiquetas masivas)

- ✅ Precios de compra/venta en el artículo (se actualizan con cada ingreso)
- ✅ Anular venta devuelve stock; anular ingreso descuenta (bloquea si el stock ya se vendió)
- ✅ **Eliminar** venta/compra definitivamente (solo permiso `acceso`): revierte stock si el documento seguía vigente, no lo toca dos veces si ya estaba anulado, y se bloquea si hay pagos aplicados o movimientos en una caja cerrada
- ✅ Ventas/compras al **crédito** → generan automáticamente cuentas por cobrar/pagar
- ✅ Ventas/compras al contado con caja abierta → movimiento automático de caja (por medio de pago: efectivo, tarjeta, transferencia, Yape, Plin)
- ✅ Módulo **Ajustes de inventario** (entradas/salidas manuales: conteo, merma, vencimiento, devoluciones, uso interno) con motivo y costo; incluido en kardex
- ✅ Baja lógica de clientes/proveedores (conservan historial)
- ✅ Cuentas: historial de pagos, anulación, filtro por estado, días para vencer
- ✅ Caja: resumen por medio de pago, historial global para admin, detalle imprimible
- ✅ Dashboard: alertas (stock agotado/bajo mínimo, CxC/CxP vencidas, caja), utilidad del periodo, ventas por medio de pago, por vendedor, por hora
- ✅ Permisos ampliados (caja, inventario, reportes, empresa, backup, cuentas) visibles en el formulario de usuario
- ✅ Panel de auditoría (admin): quién hizo qué y cuándo
- ✅ Impresión masiva de etiquetas con código de barras (`vistas/etiquetas.php`)
- ✅ Cotizaciones/proformas (`vistas/cotizacion.php`, PDF `reportes/exCotizacion.php`, conversión a venta desde el POS)
- ✅ Importación desde Excel/CSV (`vistas/importar.php`): artículos, clientes y proveedores, sin librerías externas
- 💡 Múltiples almacenes · 💡 Lotes y fechas de vencimiento · 💡 Notificaciones por correo (stock bajo, vencimientos) · 💡 API REST para app móvil

## Fase 3 — UI/UX (vendible) ✅ (pendientes: capturas reales, modo oscuro)

- ✅ **Landing page** (`index.php`): hero, beneficios, módulos, capturas, planes/CTA de demo, contacto, botón "Ingresar"
- ✅ **Login** rediseñado: marca dinámica, feedback de errores/bloqueo, mostrar contraseña, accesible
- ✅ **Layout del panel**: header con buscador global y accesos rápidos (Nueva venta / Nueva compra), sidebar con iconos por módulo y estado activo, breadcrumbs, títulos por página
- ✅ **Tema** `custom-theme.css` v2: tipografía, tarjetas, botones con estados (hover/focus/loading), badges, tablas responsivas, estados vacíos, skeletons
- ✅ **Dashboard** ejecutivo: KPIs con tendencia, alertas accionables, atajos
- ✅ **POS de ventas**: layout en dos columnas (detalle + resumen fijo), medios de pago, crédito, atajos de teclado visibles
- ✅ **Formularios**: validación en vivo, confirmaciones consistentes (`appConfirm`), toasts, manejo global de 401/403/419
- ✅ Página "Sin acceso" y errores amigables
- ✅ Perfil de usuario con cambio de contraseña
- 💡 Modo oscuro · 💡 PWA (instalable en tablet) · 💡 Multi-idioma

## Fase 4 — Calidad, despliegue y documentación 🔄

- ✅ README comercial actualizado (capturas, instalación, licenciamiento)
- ✅ Guía de despliegue `docs/DESPLIEGUE.md` (instalador, manual, actualización v1→v2, checklist de producción, cPanel, VPS)
- ✅ Instalador web `instalar.php` + esquema base `scripts/sql/esquema_base.sql` (crea BD, admin bcrypt, `config/local.php`, `instalar.lock`)
- ✅ Datos de demo `scripts/sql/demo.sql` (ferretería: 14 artículos, 7 personas, 3 compras, 7 ventas, crédito, caja, ajuste)
- ✅ Prueba de humo automatizada `scripts/smoke.php` (login/CSRF, 21 vistas, 19 endpoints, flujo caja→venta→crédito→abono→ajuste→compra→anulaciones, PDFs; limpia todo)
- ⬜ Limpieza del repo: quitar `liquidacion_beneficios_*.txt` (archivo personal ajeno al proyecto), dumps `.sql` sueltos y `1776716532.jpeg` en raíz (decisión del dueño)
- ⬜ Commit y push de la versión 2.0 (pendiente de revisión del dueño)
- ✅ CI en GitHub Actions (`.github/workflows/lint.yml`: php -l + node --check)
- 💡 Docker Compose para demo

## Orden de ejecución recomendado

1. Fase 1 completa (sin esto no se puede vender ni instalar en un cliente).
2. Fase 2: primero anulaciones/stock y precios (bugs de negocio), luego crédito/caja, luego ajustes y dashboard.
3. Fase 3: landing + login (primera impresión comercial), luego layout y módulos.
4. Fase 4: README, instalador y demo.

## Registro de avances

## Fase 5 — Perfiles por rubro ✅

Un solo sistema que se adapta al giro del cliente. El código pregunta por la **capacidad**, no por el rubro (`negocioTiene('lotes')`), para poder combinar rubros nuevos sin tocar los módulos.

- ✅ **Perfil base**: columna `tipo_negocio`, `config/negocio.php` (capacidades y textos por rubro), selector en Configuración → Empresa, elección en el instalador, `window.appNegocio` / `appNegocioTiene()` en el frontend
- ✅ **Abarrotes** (migración `20260917_abarrotes.sql`): lotes con código y vencimiento al comprar o por ajuste de entrada; salida FEFO en ventas y ajustes con trazabilidad por lote; lo vencido no se vende; anular/eliminar devuelve a los mismos lotes y bloquea compras con lotes consumidos; pantalla **Vencimientos** con baja de lotes; alertas en campana y escritorio con días configurables; lotes en la ficha del artículo
- ✅ **Ferretería** (migración `20260916_ferreteria.sql`): cantidades con decimales según la unidad de medida (`permite_fraccion`); presentaciones con equivalencia, precio y código de barras propios (venta, compra, cotización, ticket/PDF, kardex y reportes en unidades base); precio por mayor por escalas aplicado automáticamente en venta y cotización; ajustes e importación con decimales
- ✅ **Ropa** (migración `20260918_ropa.sql`): tallas y colores con stock, código de barras y precio propios; generador de combinaciones en la ficha; temporada y colección; venta/compra/cotización/ajustes por talla/color con tope de stock por combinación; anular y eliminar devuelven a la combinación; kardex, comprobantes y listados muestran la talla/color; etiquetas por combinación; importación con columnas Talla y Color; alertas de combinaciones agotadas

| Fecha | Avance |
|-------|--------|
| 2026-09-11 | Diagnóstico completo; infraestructura de seguridad; migración v2 aplicada; refactor de backend (28 archivos) a consultas preparadas |
| 2026-09-18 | Rubro ropa completo: variantes talla/color con stock propio en todo el circuito, importación y etiquetas; kardex con decimales y costo por factor corregidos; smoke a 163 comprobaciones + bancos JS (18 + 13) |
| 2026-09-17 | Rubro abarrotes completo: lotes, vencimientos, FEFO, bajas y alertas; corrección de transacción en ajustes (un rechazo tras escribir ya no confirma el stock); smoke a 136 comprobaciones |
| 2026-09-16 | Rubro ferretería completo: fracciones, presentaciones/equivalencias y precio por mayor en artículos, ventas, compras, cotizaciones, ajustes, importación, comprobantes y reportes; triggers con factor; smoke a 111 comprobaciones + banco de pruebas JS del detalle (18) |
| 2026-09-15 (noche) | Responsive verificado en 360–1024 px (sin desbordes); **perfil de negocio**: migración `20260915_perfil_negocio.sql`, `config/negocio.php` con capacidades por rubro, selector en Configuración e instalador |
| 2026-09-15 | Legibilidad de badges de estado; botones de acción de tablas rediseñados y semántica de color unificada; eliminación definitiva de ventas/compras con reversión de stock; interfaz de compra y venta ampliada; smoke a 87 comprobaciones |
| 2026-09-13 (noche) | Cotizaciones e importación desde Excel/CSV; migración `20260913_cotizaciones.sql`; smoke ampliado a 72 comprobaciones |
| 2026-09-13 (tarde) | Fase 4: instalador web, esquema base, datos demo, smoke automatizado, guía de despliegue, etiquetas masivas, CI |
| 2026-09-13 | UI v2 completa (landing, login, layout, 21 vistas), módulos Ajustes de inventario y Auditoría, reportes saneados; pruebas de humo y flujo transaccional OK; README v2 |
