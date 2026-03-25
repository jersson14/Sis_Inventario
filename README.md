# MI_TIENDA - Sistema de Inventario, Compras y Ventas

Aplicacion web en PHP (sin framework) para gestionar:
- Inventario por articulos y categorias
- Unidades de medida administrables
- Compras e ingresos
- Ventas
- Clientes y proveedores
- Reportes (tabla + exportacion + PDF)
- Configuracion de empresa (logo, colores, series, moneda)
- Modulos pro (kardex, alertas, utilidad, cuentas por cobrar/pagar, etc.)

## 1) Tecnologias usadas

### Backend
- PHP 8.x (probado en esta maquina con PHP 8.2.12)
- MySQL / MariaDB
- mysqli (conexion nativa)
- Arquitectura tipo MVC simple:
  - `modelos/`
  - `ajax/` (controladores endpoint)
  - `vistas/`

### Frontend
- AdminLTE (tema base)
- Bootstrap 3
- jQuery
- DataTables + Buttons (Excel/CSV/PDF)
- Chart.js (dashboard)
- Bootbox
- Bootstrap Select

### Reportes
- FPDF (`fpdf181/`)
- Ticket HTML imprimible (`reportes/exTicket.php`)

## 2) Estructura del proyecto

```text
mi_tienda/
├─ ajax/                  # Endpoints (JSON/HTML) por modulo
├─ config/                # Conexion y configuracion global
├─ files/                 # Archivos subidos (usuarios, articulos, backups)
├─ fpdf181/               # Libreria PDF
├─ migrations/            # Scripts SQL incrementales
├─ modelos/               # Logica y consultas SQL
├─ public/                # CSS/JS/assets de UI
├─ reportes/              # Reportes PDF/print
├─ vistas/                # Pantallas del sistema
├─ mi_tienda.sql          # Dump recomendado (base completa actual)
└─ index.php              # Redirige a vistas/login.html
```

## 3) Requisitos para correrlo

- PHP >= 8.1
- MySQL >= 5.7 o MariaDB equivalente
- Apache (ej. XAMPP)
- Extensiones PHP comunes:
  - `mysqli`
  - `mbstring`
  - `gd` (recomendado para imagenes)

## 4) Instalacion local (XAMPP)

1. Copiar el proyecto a:
   - `C:\xampp\htdocs\mi_tienda`
2. Crear la BD:
   - `mi_tienda`
3. Importar SQL principal:
   - `mi_tienda.sql` (recomendado)
4. Levantar Apache + MySQL.
5. Abrir:
   - `http://localhost/mi_tienda`

## 5) Credenciales de prueba

En el dump existen usuarios de ejemplo con clave SHA256 compartida.
El hash corresponde a la clave plana:
- `12345`

Ejemplo de login que viene en el dump:
- usuario: `jersson123`
- clave: `12345`

## 6) Donde cambiar la conexion para produccion (IMPORTANTE)

Archivo:
- `config/global.php`

Constantes a cambiar:
- `DB_HOST`
- `DB_NAME`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_ENCODE` (normalmente `utf8`)

Ejemplo:

```php
define("DB_HOST", "localhost");
define("DB_NAME", "mi_tienda_prod");
define("DB_USERNAME", "usuario_prod");
define("DB_PASSWORD", "clave_super_segura");
define("DB_ENCODE", "utf8");
```

Luego verificar que `config/Conexion.php` conecte correctamente.

## 7) Paso a produccion (guia rapida)

1. Subir codigo al servidor (sin `.git`).
2. Crear base de datos y usuario en hosting/cPanel.
3. Importar `mi_tienda.sql`.
4. Editar `config/global.php` con credenciales reales.
5. Verificar permisos de escritura:
   - `files/articulos/`
   - `files/usuarios/`
   - `files/empresa/` (si no existe, se crea al subir logo desde panel)
   - `files/backups/`
6. Probar login, ventas, compras y reportes PDF.
7. Activar HTTPS y revisar `display_errors=Off` en produccion.

## 8) Migraciones (si vienes de version antigua)

Si partes de un esquema viejo (`mitienda.sql` o similar), aplicar en este orden:

1. `migrations/20260321_unidades_medida.sql`
2. `migrations/20260321_fase_comercial.sql`

Notas:
- Los scripts son idempotentes (pensados para reintentos).
- Si usas `mi_tienda.sql` actual, normalmente ya no necesitas estas migraciones.

## 9) Configuracion de marca (logo, colores, moneda)

Se maneja desde modulo **Configuracion de Empresa**.
Se guarda en tabla:
- `configuracion_empresa`

Campos importantes:
- `logo`
- `color_primario`
- `color_secundario`
- `serie_boleta`, `serie_factura`, `serie_ticket`
- `impuesto_default`
- `moneda`

Impacto:
- Login
- Header/menu/panel
- Reportes
- Montos en modulos (segun moneda configurada)

## 10) Modulos pro y estado actual

Hay modulos comerciales avanzados (kardex, alertas, utilidad, cuentas por cobrar/pagar, compras sugeridas).

El modulo de caja en esta version puede estar deshabilitado por bandera en:
- `vistas/caja.php`

Variable:
```php
$moduloCajaHabilitado = false;
```

## 11) Seguridad minima recomendada antes de entregar

- Cambiar todas las claves demo.
- Forzar claves nuevas por usuario.
- Usar HTTPS.
- Limitar acceso a phpMyAdmin.
- Respaldos periodicos de BD.

Para cambiar clave por SQL (SHA256):

```sql
UPDATE usuario
SET clave = SHA2('NuevaClaveSegura123', 256)
WHERE login = 'jersson123';
```

## 12) Checklist final para tu pupilo

- [ ] Proyecto subido al servidor
- [ ] BD importada
- [ ] `config/global.php` configurado
- [ ] Login funciona
- [ ] Logo y datos de empresa configurados
- [ ] Ventas/compras registran correctamente
- [ ] Reportes PDF/ticket generan sin error
- [ ] Permisos de carpetas `files/*` correctos

---

Si luego quieres, te armo una segunda version del README orientada a cliente final (manual de uso por modulo) y otra tecnica para desarrolladores (debug, estructura SQL, convenciones, roadmap).
