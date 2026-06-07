# Sistema de Gestión Comercial — Mi Tienda

Sistema web de gestión empresarial (inventario, compras, ventas y reportes) desarrollado en PHP puro sin frameworks. Orientado a pequeñas y medianas empresas que necesitan controlar su stock, facturación y cuentas desde un solo panel.

---

## Capturas de pantalla

> *Agregar imágenes del dashboard, módulo de ventas y reportes.*

---

## Características principales

- **Dashboard** con gráficos de ventas y compras por periodo (Chart.js)
- **Gestión de artículos** con categorías, unidades de medida e imagen de producto
- **Compras e ingresos** con detalle por proveedor y fecha
- **Ventas** con emisión de boleta, factura y ticket de caja imprimible
- **Clientes y proveedores** con historial de transacciones
- **Reportes en PDF** (FPDF) y exportación a Excel/CSV (DataTables Buttons)
- **Cuentas por cobrar y por pagar**
- **Gestión de usuarios y permisos** por módulo (escritorio, almacén, compras, ventas, acceso)
- **Configuración de empresa**: logo, colores, series de comprobantes, moneda e impuesto
- **Backup de base de datos** desde el panel
- **Moneda configurable**: S/, $, € y otras

---

## Tecnologías

| Capa           | Herramientas                                        |
| -------------- | --------------------------------------------------- |
| Backend        | PHP 8.x, MySQL / MariaDB, mysqli nativo             |
| Frontend       | AdminLTE, Bootstrap 3, jQuery, DataTables, Chart.js |
| Reportes       | FPDF, ticket HTML imprimible                        |
| Arquitectura   | MVC simple: `modelos/` · `ajax/` · `vistas/`        |

---

## Estructura del proyecto

```text
mi_tienda/
├── ajax/           # Endpoints JSON/HTML por módulo
├── config/         # Conexión y configuración global
├── files/          # Archivos subidos (imágenes, empresa)
├── fpdf181/        # Librería PDF
├── migrations/     # Scripts SQL incrementales
├── modelos/        # Lógica de negocio y consultas
├── public/         # Assets de UI (CSS, JS, imágenes)
├── reportes/       # Reportes PDF y ticket de impresión
├── vistas/         # Vistas del sistema
└── index.php       # Punto de entrada
```

---

## Instalación local

### Requisitos

- PHP >= 8.1 con extensiones `mysqli`, `mbstring`, `gd`
- MySQL >= 5.7 o MariaDB equivalente
- Apache (recomendado: XAMPP)

### Pasos

1. Clonar el repositorio en `C:\xampp\htdocs\mi_tienda`
1. Crear la base de datos `mi_tienda` en MySQL
1. Importar el script SQL (ver sección siguiente)
1. Editar la configuración de conexión:

   ```php
   // config/global.php
   define("DB_HOST",     "localhost");
   define("DB_NAME",     "mi_tienda");
   define("DB_USERNAME", "root");
   define("DB_PASSWORD", "");
   define("DB_ENCODE",   "utf8");
   ```

1. Levantar Apache y MySQL, luego abrir [http://localhost/mi_tienda](http://localhost/mi_tienda)

### Base de datos

El script SQL **no se incluye en el repositorio** por seguridad. Solicitarlo al autor o generarlo desde el módulo de **Backup** del propio sistema.

---

## Credenciales de demo

| Campo   | Valor        |
| ------- | ------------ |
| Usuario | `jersson123` |
| Clave   | `12345`      |

> Cambiar las credenciales antes de cualquier despliegue en producción.

---

## Módulos del sistema

| Módulo      | Descripción                                        |
| ----------- | -------------------------------------------------- |
| Escritorio  | Dashboard con métricas y gráficos                  |
| Artículos   | CRUD de productos con stock e imagen               |
| Categorías  | Clasificación de artículos                         |
| Unidades    | Unidades de medida configurables                   |
| Proveedores | Gestión de proveedores                             |
| Clientes    | Gestión de clientes                                |
| Compras     | Registro de ingresos de mercadería                 |
| Ventas      | Emisión de comprobantes y ticket                   |
| Consultas   | Reportes por fecha, cliente y proveedor            |
| Cuentas     | Cuentas por cobrar y por pagar                     |
| Usuarios    | Gestión de usuarios y permisos por módulo          |
| Empresa     | Configuración de marca, moneda e impuesto          |
| Backup      | Exportación de base de datos                       |

---

## Autor

**Jersson Corilla** — Desarrollador web · soporte@2cloud.pe
