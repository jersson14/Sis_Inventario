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
vistas/*.php         Páginas del panel. Cada una: requiereLogin(false) → header.php → contenido → footer.php → scripts/<modulo>.js
vistas/scripts/*.js  Lógica de cada módulo (jQuery, DataTables, llamadas a ajax/)
ajax/*.php           Controladores: 1 archivo por módulo, switch($_GET['op']). Responden texto plano (CRUD) o JSON (listados/transacciones)
modelos/*.php        Clases con SQL (consultas preparadas vía config/Conexion.php)
config/global.php    Constantes (BD, nombre app, sesión). Sobreescribir con config/local.php (gitignored)
config/Conexion.php  mysqli + helpers dbQuery/dbRow/dbAll/dbValue/dbExec/dbInsert/dbTransaccion + moneda
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
4. **Permisos** (tabla `permiso`, ids fijos en `mapaPermisos()`): escritorio(1) almacen(2) compras(3) ventas(4) acceso(5=admin) consultac(6) consultav(7) gestion(8) empresa(9) procenter(10) cuentas(11) backup(12) reportes(13) caja(14) inventario(15). `acceso` habilita todo.
5. **Stock:** los triggers `tr_updStockIngreso` / `tr_udpStockVenta` mueven stock al insertar detalles con **`cantidad * factor`**. Anular o eliminar venta/ingreso revierte stock explícitamente (ya lo hacen los modelos). Los ajustes (`ajuste_inventario`) actualizan stock desde el modelo `Inventario`. Todo kardex debe unir ingresos + ventas + ajustes.
   - **Presentaciones:** `detalle_venta` / `detalle_ingreso` / `detalle_cotizacion` guardan la línea en la presentación vendida (2 cajas a 50.00) con `idpresentacion` y `factor` (unidades base por presentación; 1 sin presentación). En SQL: **unidades y costos** = `cantidad*factor`; **importes** = `cantidad*precio` (sin factor). El costo unitario de una compra es `precio_compra/factor`.
   - **Lotes y vencimientos** (`modelos/Lote.php`, rubros con `vencimientos`/`lotes`): la suma de `lote.stock` nunca supera `articulo.stock` (la diferencia es stock sin lote). Salidas en FEFO con `Lote::consumir()` (la venta salta lo vencido; los ajustes lo toman primero); cada consumo va a `lote_movimiento` y anular/eliminar una venta usa `Lote::revertirVenta()`. Todo lo que baje stock sin pasar por lotes debe llamar `Lote::ajustarAlStock($idarticulo)`. Una compra con lotes ya consumidos no se anula ni elimina.
   - **Transacciones:** `dbTransaccion()` solo deshace si el callback devuelve `false`. Si ya se escribió algo y hay que rechazar, devolver `false` y pasar el mensaje por referencia; nunca un array `ok=false`.
   - **Cantidades:** usar `cantidadSegura($valor, $permiteFraccion)` y `formatearCantidad()` (PHP) / `appNormalizarCantidad` y `appCantidad` (JS). Nunca `(int)round` sobre stock o cantidades.
6. **Totales** se recalculan en servidor; nunca confiar en `total_venta`/`total_compra` del cliente.
7. **Contraseñas** con `password_hash` (bcrypt). Los hashes SHA256 heredados se migran solos en el primer login válido.
8. **Auditoría:** `registrarAuditoria('modulo','accion','detalle')` en toda escritura relevante.
9. **Salida HTML:** escapar con `e()` los datos de BD que se impriman fuera de `json_encode`.
10. **Contratos frontend:** los `listar` devuelven `{sEcho,iTotalRecords,iTotalDisplayRecords,aaData}`; CRUD simple devuelve texto plano; venta/ingreso devuelven JSON `{ok,message,...}`. No cambiar sin actualizar el JS.
11. **Migraciones:** nunca editar una migración ya aplicada; crear `migrations/YYYYMMDD_descripcion.sql` idempotente (patrón `INFORMATION_SCHEMA` + `PREPARE`).
12. **Datos reales:** la BD local `mi_tienda` tiene datos del cliente. No borrar; pruebas con rollback o limpieza posterior.
13. **UI:** mantener AdminLTE/Bootstrap 3; el estilo vive en `public/css/custom-theme.css` (variables `--brand-*` inyectadas por `header.php` desde `configuracion_empresa`). Toasts con `appNotify(tipo, msg)`; confirmaciones con `appConfirm`/bootbox; botones con icono + texto + `title`.
14. **Cotizaciones** no mueven stock; se convierten en venta desde `venta.php?cotizacion=ID` (el POST de venta lleva `idcotizacion` y `ajax/venta.php` la marca CONVERTIDA). **Importación**: `modelos/Importacion.php` lee .xlsx (ZipArchive+SimpleXML) y .csv; siempre previsualizar antes de importar; los temporales viven en `files/importaciones/` (ignorado).
15. Fuera de alcance por decisión del dueño: **facturación electrónica (SUNAT)**.
16. **Perfil de negocio** (`config/negocio.php`, columna `configuracion_empresa.tipo_negocio`): el código pregunta por la **capacidad** con `negocioTiene('fracciones'|'equivalencias'|'precio_mayor'|'vencimientos'|'lotes'|'variantes'|'temporada')`, nunca por el rubro. En JS: `window.appNegocioTiene()`. Las capacidades se validan también en el servidor (con otro rubro, las presentaciones se rechazan y las cantidades se redondean a enteros).

## Estado y plan

Ver `PLAN_DE_TRABAJO.md` (fases, prioridades y checklist). Actualizarlo al cerrar tareas.
