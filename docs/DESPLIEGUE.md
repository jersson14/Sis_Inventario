# Guía de despliegue — Mi Tienda

## Opción A: instalador web (recomendada para clientes nuevos)

1. Sube todos los archivos del proyecto a la carpeta pública del servidor (`public_html/`, `htdocs/` o un subdirectorio como `/tienda`).
2. Crea (o ten a mano) un usuario de MySQL con permisos sobre la base de datos.
3. Abre en el navegador `https://tudominio.com/instalar.php`.
4. Completa: datos de MySQL, nombre del producto/empresa, administrador (contraseña de 8+ caracteres con letras y números) y, si es una demo, marca "Cargar datos de demostración".
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

## Actualizar una instalación existente (v1 → v2)

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
- [ ] Ejecutar `php scripts/smoke.php https://tudominio.com` tras cada actualización (usa y borra un usuario QA temporal).

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
