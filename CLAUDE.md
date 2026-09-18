# CLAUDE.md — Guía para trabajar en Mi Tienda

Sistema de inventario, compras y ventas para PyMEs en **PHP 8.2 puro + MySQL/MariaDB**, sin framework ni Composer. Frontend AdminLTE 2 / Bootstrap 3 / jQuery / DataTables / Chart.js. Idioma del código, comentarios, commits y docs: **español**.

## Comandos útiles

```bash
# Lint de todo el PHP propio
for f in $(find ajax config modelos reportes vistas scripts -name "*.php"); do "c:/xampp/php/php.exe" -l "$f"; done

# Arrancar MariaDB de XAMPP (puerto 3307) si no está corriendo
"c:/xampp/mysql/bin/mysqld.exe" --defaults-file="c:/xampp/mysql/bin/my.ini" --standalone &
"c:/xampp/mysql/bin/mysql.exe" -uroot -P3307 mi_tienda -e "SHOW TABLES;"

# Servidor de pruebas sin Apache (desde la raíz del proyecto)
"c:/xampp/php/php.exe" -S localhost:8080
# → http://localhost:8080/  (landing)  ·  http://localhost:8080/vistas/login.php

# Migraciones de BD (idempotentes, registradas en tabla `migracion`)
"c:/xampp/php/php.exe" scripts/migrar.php --estado
"c:/xampp/php/php.exe" scripts/migrar.php

# Prueba de humo automatizada (levanta su propio servidor, crea y borra un usuario QA)
"c:/xampp/php/php.exe" scripts/smoke.php

# Instalación limpia (instalador web) -> http://localhost:8080/instalar.php  (escribe config/local.php e instalar.lock)

# Backup manual
"c:/xampp/mysql/bin/mysqldump.exe" -uroot -P3307 --routines --triggers mi_tienda > files/backups/manual.sql
```

Prueba de humo: `scripts/smoke.php` (exit 0 = todo OK). Verifica cambios también con `php -l`, con scripts CLI temporales (en el scratchpad, `chdir('ajax')` antes de requerir modelos) y probando en el navegador.

## Arquitectura (MVC casero)

```
index.php            Landing pública del producto
comprobante.php      Consulta pública de una venta (la abre el QR del ticket)
vistas/*.php         Páginas del panel. Cada una: requiereLogin(false) → header.php → contenido → footer.php → scripts/<modulo>.js
vistas/scripts/*.js  Lógica de cada módulo (jQuery, DataTables, llamadas a ajax/)
ajax/*.php           Controladores: 1 archivo por módulo, switch($_GET['op']). Responden texto plano (CRUD) o JSON (listados/transacciones)
modelos/*.php        Clases con SQL (consultas preparadas vía config/Conexion.php)
config/global.php    Constantes (BD, nombre app, sesión). Sobreescribir con config/local.php (gitignored)
config/Conexion.php  mysqli + helpers dbQuery/dbRow/dbAll/dbValue/dbExec/dbInsert/dbTransaccion + moneda
config/marca.php     Logo, nombre y colores de la empresa (landing, login, panel, ticket, PDF)
config/seguridad.php Sesión endurecida, CSRF, requiereLogin/requierePermiso, bloqueo de login, auditoría, subida de imágenes
reportes/*.php       PDF (FPDF en fpdf181/) y ticket HTML imprimible
migrations/*.sql     Cambios de esquema versionados; scripts/migrar.php los aplica; scripts/sql/esquema_base.sql = esquema completo para instalación nueva; scripts/sql/demo.sql = datos de demo
instalar.php        Instalador web (borrar en producción tras instalar)
docs/DESPLIEGUE.md   Guía de despliegue y checklist de producción
public/              Assets (css/custom-theme.css es el tema propio; js/app-*.js helpers globales)
files/               Subidas (articulos/, usuarios/, empresa/) y backups/ — no versionados
logs/app.log         Errores SQL y de aplicación (no versionado)
```

Rutas relativas: los `ajax/` y `vistas/` requieren `../config/...` y `../modelos/...`; los modelos hacen `require_once "../config/Conexion.php"`. Por eso los scripts CLI deben `chdir` a `ajax/`.

## Reglas obligatorias

1. **SQL siempre preparado.** `dbQuery($sql, [$params])`, `dbRow`, `dbAll`, `dbExec`, `dbInsert`. Nunca interpolar variables en SQL. `limpiarCadena()` solo hace trim + htmlspecialchars (no escapa SQL).
2. **Todo endpoint `ajax/` empieza con** `require_once "../config/seguridad.php"; requiereLogin(); requierePermiso([...]);` salvo `usuario.php?op=verificar|salir` y `empresa.php?op=publicBrand`. Toda vista empieza con `requiereLogin(false)` y verifica su permiso con `usuarioTienePermiso()`.
3. **CSRF:** las peticiones POST deben llevar el header `X-CSRF-Token` (lo añade `$.ajaxSetup` en `footer.php`) o el campo `_csrf`. Los formularios HTML nuevos deben incluir `<input type="hidden" name="_csrf" value="<?php echo csrfToken(); ?>">` si no pasan por jQuery.
4. **Permisos** (tabla `permiso`, ids fijos en `mapaPermisos()`): escritorio(1) almacen(2) compras(3) ventas(4) acceso(5=admin) consultac(6) consultav(7) gestion(8, sin uso) empresa(9) procenter(10) cuentas(11) backup(12) reportes(13) caja(14) inventario(15) anular(16) precios(17) almacenes(18). `acceso` habilita todo.
   - Los permisos se releen de la BD en cada `requiereLogin()`: quitar uno o desactivar al usuario surte efecto sin cerrar sesión.
   - **Roles** = plantillas de permisos (`plantillasRol()`), no una tabla aparte. Textos de cada permiso en `descripcionesPermisos()`.
   - **Vendedor** (ventas + caja): solo ve sus ventas (`puedeVerTodasLasVentas()` = acceso o consultav; aplicar también en detalle, ticket y PDF), vende solo con su caja abierta (el administrador está exento) y sin `precios` solo a precio de lista y sin descuento (`Articulo::validarPreciosDeLista()` en venta y cotización; se aceptan las líneas de una cotización vigente). Anula solo con usuario y clave de un encargado (`autorizacionEncargado()`, cuenta como intento de login, se audita con motivo). Cuentas por cobrar/pagar exige `cuentas`.
   - Tras el login y en `escritorio.php` sin permiso se redirige a `paginaInicioUsuario()`.
   - **Caja:** el efectivo esperado del arqueo cuenta solo movimientos en `EFECTIVO` (`Caja::efectivoEsperado()`); Yape, tarjeta, transferencia y depósito se informan aparte.
   - **Arqueo ciego** (`configuracion_empresa.arqueo_ciego`, activado por defecto): a quien no es administrador `ajax/caja.php` no le envía totales, efectivo esperado ni diferencia (estado, cierre, historial y detalle).
5. **Stock:** los triggers `tr_updStockIngreso` / `tr_udpStockVenta` mueven stock al insertar detalles con **`cantidad * factor`**. Anular o eliminar venta/ingreso revierte stock explícitamente (ya lo hacen los modelos). Los ajustes (`ajuste_inventario`) actualizan stock desde el modelo `Inventario`. Todo kardex debe unir ingresos + ventas + ajustes.
   - **Presentaciones:** `detalle_venta` / `detalle_ingreso` / `detalle_cotizacion` guardan la línea en la presentación vendida (2 cajas a 50.00) con `idpresentacion` y `factor` (unidades base por presentación; 1 sin presentación). En SQL: **unidades y costos** = `cantidad*factor`; **importes** = `cantidad*precio` (sin factor). El costo unitario de una compra es `precio_compra/factor`.
   - **Lotes y vencimientos** (`modelos/Lote.php`, rubros con `vencimientos`/`lotes`): la suma de `lote.stock` nunca supera `articulo.stock` (la diferencia es stock sin lote). Salidas en FEFO con `Lote::consumir()` (la venta salta lo vencido; los ajustes lo toman primero); cada consumo va a `lote_movimiento` y anular/eliminar una venta usa `Lote::revertirVenta()`. Todo lo que baje stock sin pasar por lotes debe llamar `Lote::ajustarAlStock($idarticulo)`. Una compra con lotes ya consumidos no se anula ni elimina.
   - **Tallas y colores** (`modelos/Variante.php`, tabla `articulo_variante`): en un artículo con variantes activas `articulo.stock` = suma de sus variantes. Los triggers mueven artículo y variante con un UPDATE multitabla (`idvariante` en los detalles); anular/eliminar/ajustar usa `Variante::moverStock()`. Un artículo con variantes **exige** `idvariante` en venta, compra, cotización y ajuste en cualquier rubro (`Variante::resolverDetalle()`); el rubro solo decide si se pueden crear. Las variantes con stock no se quitan; sin stock se desactivan.
   - **Toma de inventario** (`modelos/Conteo.php`): un solo conteo ABIERTO a la vez; cada línea guarda `stock_sistema` de su última lectura y al aplicar se ajusta `contado - stock_sistema` sobre el stock actual. Todo movimiento de ajuste pasa por `Inventario::moverStock()` (dentro de `dbTransaccion`), que maneja variantes y lotes (`auto` FEFO, `lote`, `sin_lote`, `nuevo`). Códigos escaneados: `Inventario::resolverCodigo()` (exacto: talla, artículo, caja, lote). Lector en JS: `appLectorCodigo(selector, fn, {enter})`.
   - **Transacciones:** `dbTransaccion()` solo deshace si el callback devuelve `false`. Si ya se escribió algo y hay que rechazar, devolver `false` y pasar el mensaje por referencia; nunca un array `ok=false`.
   - **Almacenes** (`modelos/Stock.php`, tablas `almacen` y `stock_almacen` por artículo y `idvariante`, 0 = sin talla): `articulo.stock` (y el de cada variante) = suma de sus almacenes, incluido el oculto "En tránsito". Los triggers suman/restan también en el `idalmacen` del documento (NULL = principal). Todo movimiento de stock fuera de los triggers pasa por `Stock::mover($art, $var, $delta, $idalmacen)`; lo que cambie el total sin almacén (ficha, importación, SQL) se cuadra en el principal con `Stock::cuadrar`/`asegurar`. Las validaciones de stock usan `Stock::enAlmacen()`, no `articulo.stock`. Venta, compra, ajuste, conteo y NC guardan su `idalmacen` (el de trabajo: `Stock::almacenActual()` = sesión → `usuario.idalmacen` → principal); los lotes tienen almacén (`Lote::consumir(..., $idalmacen)`). Transferencias (`modelos/Transferencia.php`): ENVIADA mueve origen→tránsito, RECIBIDA tránsito→destino (faltante = ajuste SALIDA en tránsito), ANULADA devuelve. El kardex sale de la vista `kardex_movimiento` (con `idalmacen` y filas `TRASLADO -`/`TRASLADO +`; sin filtro de almacén se omiten los traslados). Con un solo almacén la interfaz no muestra nada de esto (`Stock::multiAlmacen()`).
   - **Cantidades:** usar `cantidadSegura($valor, $permiteFraccion)` y `formatearCantidad()` (PHP) / `appNormalizarCantidad` y `appCantidad` (JS). Nunca `(int)round` sobre stock o cantidades.
6. **Totales** se recalculan en servidor; nunca confiar en `total_venta`/`total_compra` del cliente.
   - **Comprobante público (QR):** `comprobante.php?c=CLAVE` y `reportes/exFactura.php?c=CLAVE` son las únicas páginas de ventas sin login; buscan solo por `venta.codigo_publico` (`Venta::codigoPublico()` / `idPorCodigoPublico()`, 16 caracteres aleatorios), nunca por `idventa`. No mostrar ahí datos internos (cajero, costos, caja). El QR se genera con `config/qr.php` (sin internet) y la URL con `urlComprobantePublico()` (`config/comprobante.php`, usa `configuracion_empresa.url_publica`).
   - **Comprobantes de venta:** el número lo asigna siempre el servidor (siguiente correlativo con bloqueo); nunca se acepta uno escrito a mano. El IGV lo fija el tipo: Boleta y Factura usan `impuesto_default` de la empresa (los precios lo incluyen; solo cambia el desglose), la nota de venta (Ticket) va con 0. El impuesto se guarda en porcentaje (18, nunca 0.18). Boletas y facturas no se eliminan (dejarían huecos en la numeración): se anulan; solo la nota de venta se elimina.
   - **Pagos de venta:** cada cobro es una fila de `venta_pago` (pago mixto; `venta.medio_pago` = MIXTO si hay varios, CREDITO sin adelanto). El vuelto solo sale del efectivo; la caja lleva un movimiento por medio. El medio `NOTA_CREDITO` consume saldo a favor (`NotaCredito::consumirSaldo`, mismo cliente) y no mueve caja.
   - **Devoluciones** (`modelos/NotaCredito.php`, serie NC01 con correlativo bloqueado): por línea con tope (vendido − devuelto), descuento prorrateado, stock a la misma talla, lote y almacén (salvo "dañado"). Venta al crédito: primero baja la cuenta por cobrar; el resto se reintegra por medio (caja EGRESO ref `NC-{id}`) o queda como saldo a favor. `Venta::anular()` emite una NC tipo 01 por el total; con devoluciones parciales no se anula. Los reportes de ventas/utilidad usan las vistas `venta_total` y `venta_linea` (restan las NC).
7. **Contraseñas** con `password_hash` (bcrypt). Los hashes SHA256 heredados se migran solos en el primer login válido.
8. **Auditoría:** `registrarAuditoria('modulo','accion','detalle')` en toda escritura relevante.
9. **Salida HTML:** escapar con `e()` los datos de BD que se impriman fuera de `json_encode`.
10. **Contratos frontend:** los `listar` devuelven `{sEcho,iTotalRecords,iTotalDisplayRecords,aaData}`; CRUD simple devuelve texto plano; venta/ingreso devuelven JSON `{ok,message,...}`. No cambiar sin actualizar el JS.
11. **Migraciones:** nunca editar una migración ya aplicada; crear `migrations/YYYYMMDD_descripcion.sql` idempotente (patrón `INFORMATION_SCHEMA` + `PREPARE`).
12. **Datos reales:** la BD local `mi_tienda` tiene datos del cliente. No borrar; pruebas con rollback o limpieza posterior.
13. **Marca:** logo, nombre y colores de la empresa salen solo de `config/marca.php` (`marcaEmpresa()`, `marcaUrlLogo($prefijo)`, `marcaRutaLogoPdf()` para FPDF). Nunca poner logos o datos de otra empresa como respaldo.
14. **UI:** mantener AdminLTE/Bootstrap 3; el estilo vive en `public/css/custom-theme.css` (variables `--brand-*` inyectadas por `header.php` desde `configuracion_empresa`). Toasts con `appNotify(tipo, msg)`; confirmaciones con `appConfirm`/bootbox; botones con icono + texto + `title`.
15. **Cotizaciones** no mueven stock; se convierten en venta desde `venta.php?cotizacion=ID` (el POST de venta lleva `idcotizacion` y `ajax/venta.php` la marca CONVERTIDA). **Importación**: `modelos/Importacion.php` lee .xlsx (ZipArchive+SimpleXML) y .csv; siempre previsualizar antes de importar; los temporales viven en `files/importaciones/` (ignorado).
16. Fuera de alcance por decisión del dueño: **facturación electrónica (SUNAT)**.
17. **Perfil de negocio** (`config/negocio.php`, columna `configuracion_empresa.tipo_negocio`): el código pregunta por la **capacidad** con `negocioTiene('fracciones'|'equivalencias'|'precio_mayor'|'vencimientos'|'lotes'|'variantes'|'temporada')`, nunca por el rubro. En JS: `window.appNegocioTiene()`. Las capacidades se validan también en el servidor (con otro rubro, las presentaciones se rechazan y las cantidades se redondean a enteros).

## Estado y plan

Ver `PLAN_DE_TRABAJO.md` (fases, prioridades y checklist). Actualizarlo al cerrar tareas.
