# Guía de despliegue — Mi Tienda

## Opción A: instalador web (recomendada para clientes nuevos)

1. Sube todos los archivos del proyecto a la carpeta pública del servidor (`public_html/`, `htdocs/` o un subdirectorio como `/tienda`).
2. Crea (o ten a mano) un usuario de MySQL con permisos sobre la base de datos.
3. Abre en el navegador `https://tudominio.com/instalar.php`.
4. Completa: datos de MySQL, nombre del producto/empresa, administrador (contraseña de 8+ caracteres con letras y números), **tipo de negocio** (General, Abarrotes, Ferretería o Ropa) y, si es una demo, marca "Cargar datos de demostración".
   El tipo de negocio decide qué funciones se habilitan y puede cambiarse después en
   *Configuración → Empresa y marca*.
5. El instalador crea la BD, carga `scripts/sql/esquema_base.sql`, el administrador con bcrypt, escribe `config/local.php` y crea `instalar.lock`.
6. **Elimina `instalar.php`** del servidor.

## Opción B: manual

```bash
mysql -u usuario -p -e "CREATE DATABASE mi_tienda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u usuario -p mi_tienda < scripts/sql/esquema_base.sql
mysql -u usuario -p mi_tienda < scripts/sql/demo.sql        # opcional
cp config/local.example.php config/local.php                 # editar credenciales y APP_ENV
php scripts/migrar.php                                       # marca las migraciones como aplicadas
```

Crear el administrador (la clave SHA256 se migra a bcrypt en el primer login):

```sql
INSERT INTO usuario(nombre,tipo_documento,num_documento,login,clave,imagen,condicion)
VALUES('Administrador','DNI','','admin',SHA2('CambiaEsto123',256),'',1);
INSERT INTO usuario_permiso(idusuario,idpermiso) SELECT LAST_INSERT_ID(), idpermiso FROM permiso;
```

## Actualizar una instalación existente (2.0 → 2.3.1)

Los cambios de esquema son **idempotentes**: se pueden ejecutar varias veces sin dañar
nada, y no borran datos. Aun así, respalda antes.

1. **Backup obligatorio**: módulo *Backup* o `mysqldump -u usuario -p --routines --triggers base > copia.sql`.
2. Sube los archivos nuevos (sobrescribe).
3. Ejecuta las migraciones:

   ```bash
   php scripts/migrar.php --estado   # muestra cuáles faltan
   php scripts/migrar.php            # las aplica
   ```

   Se aplican estas: `20260915_perfil_negocio`, `20260916_ferreteria`,
   `20260917_abarrotes`, `20260918_ropa`, `20260919_pos_ticket`, `20260920_roles_permisos`, `20260921_precios_arqueo`, `20260922_igv_correlativo`.
4. Entra a *Configuración → Empresa y marca* y **elige el tipo de negocio** del cliente.
   Mientras esté en "General", el sistema funciona igual que antes.
5. Pide a los usuarios recargar con `Ctrl+F5` la primera vez (el CSS y el JS llevan
   número de versión, así que normalmente se actualizan solos).
6. Verifica con `php scripts/smoke.php https://tudominio.com`.

### Qué cambia en la base de datos

| Migración | Qué agrega |
| --- | --- |
| `20260915_perfil_negocio` | Columna `tipo_negocio` en `configuracion_empresa` |
| `20260916_ferreteria` | `permite_fraccion` en unidades; tablas `articulo_presentacion` y `articulo_precio_escala`; `idpresentacion` y `factor` en los detalles; los triggers de stock pasan a usar `cantidad * factor` |
| `20260917_abarrotes` | Tablas `lote` y `lote_movimiento`; `dias_alerta_vencimiento` en `configuracion_empresa` |
| `20260918_ropa` | Tabla `articulo_variante`; `idvariante` en detalles y ajustes; `temporada` y `coleccion` en `articulo`; los triggers mueven artículo y variante en una sola sentencia |
| `20260919_pos_ticket` | Ajustes del ticket en `configuracion_empresa` (`ticket_ancho`, `ticket_auto_imprimir`, `ticket_logo`, `ticket_cabecera`, `ticket_copias`); `num_operacion` y `monto_recibido` en `venta`; `cuenta_pago` y `num_operacion` en `ingreso` |
| `20260920_roles_permisos` | Permiso 16 "Anular documentos"; se asigna a los usuarios que ya tenían Ventas o Compras para que sigan pudiendo anular |
| `20260921_precios_arqueo` | Permiso 17 "Cambiar precios y descuentos" (se asigna a quienes ya tenían Ventas); columna `arqueo_ciego` en `configuracion_empresa` (activada) |
| `20260922_igv_correlativo` | Corrige documentos antiguos con el impuesto guardado como fracción (0.18 → 18). No cambia totales ni numeración |

Tras la 2.1, configura la ticketera de cada caja (papel, impresora predeterminada,
apertura del cajón y `--kiosk-printing`) como explica el manual, sección *Ticketera*.

Tras la 2.2, revisa en *Configuración → Usuarios* los permisos de cada persona: los vendedores
deben quedar con el rol **Vendedor / cajero** (sin "Anular documentos" ni "Consulta ventas").

Los datos existentes quedan con `factor = 1` y sin lotes ni variantes: el
comportamiento anterior no cambia hasta que se usen las funciones nuevas.

## Actualizar una instalación antigua (v1 → v2)

1. Haz backup: módulo Backup o `mysqldump`.
2. Sube los archivos nuevos (sobrescribe).
3. Ejecuta `php scripts/migrar.php` (o importa `migrations/20260911_seguridad_inventario.sql` con phpMyAdmin).
4. Crea `config/local.php` a partir de `config/local.example.php`.
5. Los usuarios entran con sus mismas contraseñas; el hash se migra a bcrypt automáticamente.

## Checklist de producción

- [ ] `APP_ENV => 'production'` en `config/local.php` (oculta errores; se registran en `logs/app.log`).
- [ ] HTTPS activo (las cookies de sesión se marcan `Secure` solas cuando detectan HTTPS).
- [ ] Apache con `AllowOverride All` para que apliquen los `.htaccess`. Verifica que estas URLs devuelvan 403:
  `/config/global.php`, `/modelos/Venta.php`, `/migrations/`, `/logs/app.log`, `/files/backups/`.
- [ ] En Nginx replica las reglas: denegar `config/`, `modelos/`, `migrations/`, `logs/`, `scripts/`, `*.sql`, `*.md`; no ejecutar PHP dentro de `files/`.
- [ ] Permisos de escritura solo en `files/` y `logs/` (el resto solo lectura).
- [ ] `instalar.php` eliminado.
- [ ] Contraseña del administrador cambiada desde "Mi perfil".
- [ ] Backup programado: tarea cron con `mysqldump` diario, o generar desde el panel y descargar.
- [ ] Zona horaria correcta (`APP_TIMEZONE`, por defecto `America/Lima`).
- [ ] Límites de subida en `php.ini` ≥ 8 MB (`upload_max_filesize`, `post_max_size`) para logos e imágenes.
- [ ] Ejecutar `php scripts/smoke.php https://tudominio.com` tras cada actualización (usa y borra usuarios QA temporales, admin y vendedor; 212 comprobaciones).
- [ ] Tipo de negocio elegido en *Configuración → Empresa y marca* y, si aplica, días de aviso de vencimiento.
- [ ] Entregar al cliente el manual de uso: [`MANUAL.md`](MANUAL.md).

## Servidor de pruebas sin Apache

```bash
php -S localhost:8080
# http://localhost:8080/            landing
# http://localhost:8080/vistas/login.php
```

Los `.htaccess` no aplican con el servidor embebido: úsalo solo para desarrollo.

## Hosting compartido (cPanel)

- Sube el ZIP y descomprime en `public_html/tienda`.
- Crea la BD y el usuario desde "MySQL Databases" y anótalos para `instalar.php`.
- Versión de PHP: selecciona 8.1 o superior en "Select PHP Version" con `mysqli`, `mbstring`, `gd`, `fileinfo`.

## VPS (Ubuntu + Apache)

```bash
sudo apt install apache2 php8.2 php8.2-mysqli php8.2-mbstring php8.2-gd php8.2-curl mariadb-server
sudo a2enmod rewrite headers
# En el VirtualHost: AllowOverride All
sudo certbot --apache -d tudominio.com
```
