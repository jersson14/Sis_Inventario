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

## Fase 2 — Funcionalidad de inventario completo ✅

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
- ✅ Múltiples almacenes · 💡 Lotes y fechas de vencimiento · 💡 Notificaciones por correo (stock bajo, vencimientos) · 💡 API REST para app móvil

## Fase 3 — UI/UX (vendible) ✅ (pendiente: capturas para el README)

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
- ✅ Limpieza del repo: los volcados `.sql` sueltos se movieron a `files/backups/dumps_antiguos/` (nunca estuvieron versionados); los otros archivos ya no existían
- ✅ Documentación actualizada: README con rubros y módulos nuevos, `docs/DESPLIEGUE.md` con la actualización 2.0 → 2.0.5 y qué cambia en la BD, y **manual de uso** para el cliente final (`docs/MANUAL.md`)
- ✅ Capturas de pantalla del README (landing, escritorio, punto de venta, artículos y kardex), tomadas de una instalación de demostración desechable
- ⬜ Datos de demostración por rubro (hoy `demo.sql` solo trae una ferretería)
- ⬜ Prueba de extremo a extremo hecha por el dueño (crear artículo, comprar, vender, anular, imprimir)
- ⬜ Commit y push de la versión (pendiente de revisión del dueño)
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

## Fase 6 — Punto de venta, compras a pantalla completa y ticket térmico ✅ (v2.1.0)

- ✅ **Modo caja**: el POS y la compra ocultan menú, cabecera y pie (`appModoCaja()` en `public/js/app-pos.js`) y ofrecen pantalla completa del navegador
- ✅ **POS de ventas**: cuadrícula de productos con fotos, categorías y filtro por texto; `Enter`/lector de barras agrega (códigos de caja y talla/color vía servidor); carrito con `−`/`+`; ventana de cobro con medios en botones, efectivo recibido, billetes rápidos y **vuelto**; N° de operación para Yape/Plin/tarjeta/depósito; tras cobrar queda lista la siguiente venta con acceso a reimprimir
- ✅ **Ticket térmico** (`reportes/exTicket.php`): 80 o 58 mm, negro puro, recibido/vuelto, N° de operación, copias, logo y textos configurables en *Empresa → Ticket e impresora*, ticket de prueba; se imprime solo en un iframe oculto al cobrar (preferencia por PC)
- ✅ **Compras**: pantalla completa con cabecera compacta, buscador en línea (código exacto entra directo), detalle grande con `Enter` entre cantidad → precios → buscador, medios de pago en botones con **depósito en cuenta** (cuenta del proveedor y N° de operación) y **autoguardado** del borrador en la PC con aviso para continuar
- ✅ Medio de pago `DEPOSITO` en ventas, compras, caja y cuentas; migración `20260919_pos_ticket.sql`; smoke a 173 comprobaciones
- ✅ Cierre de caja: el efectivo esperado cuenta solo EFECTIVO (resuelto en la fase 7)
- 💡 Apertura del cajón e impresión silenciosa sin depender del controlador ni de `--kiosk-printing` (agente local tipo QZ Tray con ESC/POS)
- ✅ Pago mixto (parte efectivo, parte Yape) · 💡 Autoguardado también en el POS

## Fase 7 — Roles, vendedor y marca en todo el sistema ✅ (v2.2.0)

- ✅ **Roles** como plantillas de permisos en el formulario de usuario (Vendedor/cajero, Encargado, Almacenero, Compras, Administrador) y descripción de cada permiso
- ✅ **Permisos al instante**: se releen en cada petición; quitar un permiso o desactivar al usuario lo saca sin esperar a que cierre sesión
- ✅ **Vendedor**: entra directo al POS; solo ve sus ventas (listado, resumen, detalle, ticket y PDF); no anula (permiso nuevo 16 "Anular documentos", migración `20260920_roles_permisos.sql`); vende solo con caja abierta y la abre desde el POS; Cuentas por cobrar/pagar pasa a exigir su propio permiso
- ✅ **Cierre de caja** corregido: el efectivo esperado cuenta solo EFECTIVO; Yape/tarjeta/transferencia/depósito se informan aparte
- ✅ **Marca** centralizada (`config/marca.php`): logo, nombre y colores de la empresa en landing, login, panel, ticket y PDF; se quitó el logo de otra tienda que los PDF usaban cuando la empresa no tenía logo; logos WEBP convertidos para los PDF
- ✅ Página de inicio según permisos (`paginaInicioUsuario()`); smoke a 199 comprobaciones (24 del rol vendedor)
- ⬜ Revisar los permisos de los usuarios actuales: `luis2025` (cargo "venta") tiene todos los permisos, incluido administrador
- ✅ Precio y descuento bloqueados para el vendedor, arqueo ciego y anulación con clave de encargado (fase 8)

## Fase 8 — Controles del vendedor ✅ (v2.3.0)

- ✅ Permiso 17 **"Cambiar precios y descuentos"**: sin él, venta y cotización solo aceptan el precio de lista (presentación, talla/color y precio por mayor calculados en el servidor igual que en el POS, ±1 céntimo de redondeo) y descuento 0; se respetan las líneas de una cotización vigente. En el POS y en cotizaciones el precio queda de solo lectura
- ✅ **Anular con autorización**: sin "Anular documentos" se pide motivo + usuario y clave de un encargado; los fallos cuentan como intentos de login (bloqueo) y la auditoría guarda quién pidió, quién autorizó y el motivo. Botón en el listado y en la última venta del POS
- ✅ **Arqueo ciego** (opción de empresa, activada): quien no es administrador no recibe del servidor totales, efectivo esperado ni diferencia (estado, cierre, historial, detalle, POS)
- ✅ Migración `20260921_precios_arqueo.sql`; smoke a 207 comprobaciones

## Fase 9 — Comprobante con QR y consulta pública ✅ (v2.3.3)

- ✅ **QR en el ticket y en el PDF** (`config/qr.php`, generador propio sin internet, verificado decodificando con OpenCV): lleva a `comprobante.php?c=CLAVE`, donde el cliente ve solo esa venta (sin login, DNI enmascarado, sin cajero), la imprime o descarga el PDF (`exFactura.php?c=`)
- ✅ Clave pública aleatoria de 16 caracteres por venta (`venta.codigo_publico`, se crea al imprimir); clave inventada = 404
- ✅ Aviso configurable de canje por boleta/factura electrónica SUNAT en ticket, PDF y página pública; *Empresa → Ticket*: QR sí/no, aviso y **dirección pública** (avisa si el QR apuntaría a localhost)
- ✅ Ticket más legible en térmica: Arial en negrita (antes Arial Narrow), 14 px en 80 mm, separadores gruesos
- ✅ **Ticket con formato de comprobante** (18-sep-2026): logo, RUC y teléfono en una línea, recuadro con tipo y número (Boleta de venta / Factura / Nota de venta), datos del cliente alineados (Señor(es), DNI/RUC, domicilio, fecha, hora, moneda, tipo de pago), tabla Cant./Descripción/P.Unit/Dscto/Importe (Dscto solo si hay descuentos; en 58 mm el precio va bajo el nombre), op. gravada e IGV, importe total destacado, total en letras, cajero junto al QR. Sin textos de "electrónica" ni hash SUNAT (fuera de alcance)
- ✅ Corregido el total en letras de los PDF (salía "SEISCIENTOSSEISCIENTOS … CON 00/100 CON 00/100" desde PHP 8)
- ✅ Migración `20260923_comprobante_qr.sql`; smoke a 218 comprobaciones
- ⬜ Configurar la dirección pública real (dominio o IP de red) para que el QR abra en el celular del cliente

## Fase 10 — Toma de inventario con lector, lotes y tallas ✅ (v2.3.4)

- ✅ **Toma de inventario** (`vistas/conteo.php`, `modelos/Conteo.php`): conteo físico de todo el almacén o de una categoría con el lector; por artículo, talla/color y **lote** (lotes nuevos encontrados con código y vencimiento, stock sin lote aparte); varias personas contando a la vez; diferencias en unidades y soles; pendientes por contar; vista previa y aplicación en una sola transacción (ajustes con motivo CONTEO ligados al conteo) con opción de poner en cero lo no contado; reporte imprimible con firmas (`reportes/rptconteo.php`)
- ✅ Ventas durante el conteo no descuadran: cada línea guarda el stock del sistema en su última lectura y se ajusta solo la diferencia
- ✅ **Ajuste de inventario con lector**: reconoce código de artículo, caja (suma su equivalencia), talla/color y lote; la entrada puede sumar a un lote existente (`Lote::sumarLote()`); movimiento de stock unificado en `Inventario::moverStock()`
- ✅ **Lector sin Enter** (`public/js/app-lector.js`): detecta la ráfaga del lector (o Tab al final) en POS, compras, cotizaciones, ajustes y conteo; el tecleo humano no dispara nada
- ✅ Migración `20260924_conteo_inventario.sql`; smoke a 237 comprobaciones; prueba de interfaz en Chrome simulando el lector (21 comprobaciones)

## Fase 11 — Pago mixto ✅ (v2.4)

- ✅ Tabla `venta_pago` (migración `20260925_pago_mixto.sql`, con los cobros de contado existentes pasados a una línea cada uno)
- ✅ Cobro con varias líneas (efectivo + Yape + tarjeta…): pagado, falta y vuelto; el vuelto solo sale del efectivo; crédito con adelanto (la cuenta por cobrar queda por el saldo)
- ✅ Caja: un movimiento por medio; el arqueo cuenta solo el efectivo
- ✅ Ticket, PDF, detalle y comprobante público muestran cada pago; anular revierte por medio

## Fase 12 — Devoluciones y notas de crédito ✅ (v2.5)

- ✅ Tablas `nota_credito` / `detalle_nota_credito` (serie NC01 con correlativo bloqueado) y vistas `venta_linea`, `venta_total`, `kardex_movimiento` (migración `20260926_notas_credito.sql`)
- ✅ Devolución por ítem con tope (vendido − ya devuelto), descuento prorrateado; stock a la misma talla, lote y almacén; opción "dañado" (no vuelve a stock)
- ✅ Reintegro por medio, saldo a favor (se usa como medio de pago del mismo cliente) o descuento de la deuda si fue al crédito
- ✅ Autorización de encargado sin permiso "Anular", motivo y auditoría; "Anular" emite una NC 01 por el total
- ✅ Kardex, utilidad y reportes restan las devoluciones; PDF A4 y ticket de la NC; listado *Devoluciones (notas de crédito)*

## Fase 13 — Varios almacenes y transferencias ✅ (v2.6)

- ✅ Tablas `almacen` (NORMAL y un TRANSITO oculto) y `stock_almacen` por artículo y talla; `idalmacen` en venta, compra, ajuste, conteo, NC, lote y usuario; permiso 18 "Almacenes" (migración `20260927_almacenes.sql`)
- ✅ Invariante: `articulo.stock` = suma de sus almacenes (incluido el tránsito); los triggers mueven también el almacén del documento; lo que cambia el total por fuera se cuadra en el principal (`Stock::asegurar`)
- ✅ Almacén de trabajo por sesión (selector en la cabecera y en el POS; por defecto el del usuario): venta, compra ("Entra a"), ajustes, conteo y devolución usan su almacén; aviso de stock en otros almacenes
- ✅ Transferencias ENVIADA → tránsito → RECIBIDA (faltante se da de baja) o ANULADA; "llega al instante"; con tallas y lotes (FEFO, el lote viaja con su código y vencimiento)
- ✅ Kardex con filtro por almacén (traslados como entrada/salida; con "Todos" se omiten); stock por almacén; vencimientos con almacén; baja de lote en su almacén
- ✅ Smoke a 258 comprobaciones; pruebas de backend (pago, NC, conteo, almacenes 29) y de interfaz en Chrome (22) sobre una copia de la base
- ⬜ Series, caja y vendedores por local (multisucursal completa, v3.1)

## Próximas fases (propuesta)

Detalle en [`docs/PLAN_FACTURACION_SUCURSALES.md`](docs/PLAN_FACTURACION_SUCURSALES.md):

- ✅ v2.4 Pago mixto (varios medios en una venta, adelanto en crédito)
- ✅ v2.5 Devoluciones parciales y notas de crédito
- ✅ v2.6 Varios almacenes y transferencias
- ⬜ v3.0 Facturación electrónica SUNAT con conectores por proveedor (cada empresa usa sus credenciales)
- ⬜ v3.1 Multisucursal (caja, series y vendedores por local; el stock por almacén y las transferencias ya están en v2.6)

### Antes de producción (pendiente del dueño)

- ⬜ `config/local.php` con `APP_ENV => 'production'` y usuario de MySQL propio con contraseña (hoy: `root` sin contraseña, modo development)
- ⬜ HTTPS, borrar `instalar.php`, backup automático diario (el último respaldo es del 16-sep)
- ⬜ Revisar permisos reales: `luis2025` (cargo "venta") es administrador; `antonio2021` (vendedor, inactivo) recibió "Anular" y "Precios" por compatibilidad
- ⬜ Probar con la ticketera y el cajón reales (papel, `--kiosk-printing`, apertura del cajón en el controlador)
- ⬜ Un día de marcha blanca con un vendedor real (abrir caja, vender, anular con autorización, cerrar) antes de retirar el sistema anterior
- ⬜ Commit, merge a `main` y respaldo antes de subir

| Fecha | Avance |
|-------|--------|
| 2026-09-18 (v2.6.0) | Pago mixto y adelanto en crédito, devoluciones parciales con notas de crédito (saldo a favor, dañados, autorización) y varios almacenes con transferencias en tránsito, kardex por almacén y almacén de trabajo por usuario; esquema base y demo al día; smoke a 258, pruebas de interfaz en Chrome |
| 2026-09-18 (v2.3.4) | Toma de inventario con lector (lotes, tallas, varias personas, pendientes, reporte), ajustes con lector y lotes existentes, lector sin Enter en todas las pantallas; smoke a 237 y prueba de interfaz en Chrome |
| 2026-09-18 (v2.3.3) | QR en ticket y PDF hacia la consulta pública del comprobante (ver, imprimir, descargar), aviso de canje SUNAT, ticket más legible y total en letras corregido; QR verificado con OpenCV, smoke a 218 comprobaciones |
| 2026-09-17 (v2.3.2) | Librerías de seguridad: jQuery 3.3.1 → 3.7.1 y Bootstrap 3.3.7 → 3.4.1 (fallas XSS conocidas corregidas); se cargan con `?v=` para que las cajas no usen la copia vieja en caché. Verificado en las 24 vistas, POS, compras y componentes (menús, modales, selectores, tooltips) sin errores |
| 2026-09-17 (v2.3.1) | Preparación para facturación electrónica: la boleta lleva el IGV de la empresa (el servidor lo fija por tipo de comprobante), IGV antiguo 0.18 → 18 (migración `20260922_igv_correlativo.sql`), número de comprobante siempre automático y boletas/facturas no eliminables; smoke a 212 comprobaciones |
| 2026-09-17 (cierre) | v2.3.0: precio de lista obligatorio sin permiso, anulación con clave de encargado y arqueo ciego; smoke a 207 comprobaciones y verificación en navegador |
| 2026-09-17 (noche) | v2.2.0: roles con plantillas, rol vendedor (solo sus ventas, sin anular, caja obligatoria), permisos al instante, arqueo solo de efectivo y logo de la empresa en landing, login, panel, ticket y PDF; verificado en navegador y smoke a 199 comprobaciones |
| 2026-09-17 | v2.1.0: POS con cuadrícula y cobro con vuelto, ticket térmico 80/58 mm configurable con impresión automática, compras a pantalla completa con buscador, depósito en cuenta y autoguardado; verificado en navegador (escritorio y 390 px) y smoke a 173 comprobaciones |
| 2026-09-11 | Diagnóstico completo; infraestructura de seguridad; migración v2 aplicada; refactor de backend (28 archivos) a consultas preparadas |
| 2026-09-19 | Botón Editar de artículos no abría ("No se pudo cargar el artículo", error presente desde v2.0): `appParseJson` volvía a parsear respuestas que jQuery ya entregaba como objeto; corregido en la función compartida. Auditoría en navegador de todos los botones (filas, cabeceras, ficha de artículo, etiquetas, caja) y listados de ventas/compras ordenados por fecha real |
| 2026-09-18 (noche) | Capturas del README desde una demo desechable; raíz del repo limpia; corrección del kardex (ordenaba la fecha como texto y el saldo quedaba desordenado) |
| 2026-09-18 (tarde) | Documentación al día: README (rubros, módulos, pruebas), guía de despliegue (actualización y cambios de esquema) y manual de uso para el cliente |
| 2026-09-18 | Rubro ropa completo: variantes talla/color con stock propio en todo el circuito, importación y etiquetas; kardex con decimales y costo por factor corregidos; smoke a 163 comprobaciones + bancos JS (18 + 13) |
| 2026-09-17 | Rubro abarrotes completo: lotes, vencimientos, FEFO, bajas y alertas; corrección de transacción en ajustes (un rechazo tras escribir ya no confirma el stock); smoke a 136 comprobaciones |
| 2026-09-16 | Rubro ferretería completo: fracciones, presentaciones/equivalencias y precio por mayor en artículos, ventas, compras, cotizaciones, ajustes, importación, comprobantes y reportes; triggers con factor; smoke a 111 comprobaciones + banco de pruebas JS del detalle (18) |
| 2026-09-15 (noche) | Responsive verificado en 360–1024 px (sin desbordes); **perfil de negocio**: migración `20260915_perfil_negocio.sql`, `config/negocio.php` con capacidades por rubro, selector en Configuración e instalador |
| 2026-09-15 | Legibilidad de badges de estado; botones de acción de tablas rediseñados y semántica de color unificada; eliminación definitiva de ventas/compras con reversión de stock; interfaz de compra y venta ampliada; smoke a 87 comprobaciones |
| 2026-09-13 (noche) | Cotizaciones e importación desde Excel/CSV; migración `20260913_cotizaciones.sql`; smoke ampliado a 72 comprobaciones |
| 2026-09-13 (tarde) | Fase 4: instalador web, esquema base, datos demo, smoke automatizado, guía de despliegue, etiquetas masivas, CI |
| 2026-09-13 | UI v2 completa (landing, login, layout, 21 vistas), módulos Ajustes de inventario y Auditoría, reportes saneados; pruebas de humo y flujo transaccional OK; README v2 |
