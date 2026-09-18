# Plan: facturación electrónica, multisucursal, devoluciones y pago mixto

> Estado: **propuesta para decidir** (sept. 2026) · Versión actual del sistema: 2.3.1
> Alcance: pago mixto, devoluciones parciales con notas de crédito, facturación
> electrónica SUNAT conectable por cualquier empresa, y varias sucursales con stock propio.
>
> **Importante:** los plazos, montos y catálogos de SUNAT cambian. Todo lo marcado con ⚖️
> debe confirmarse con la norma vigente y con el contador del cliente antes de programarlo.

---

## 1. Resumen y orden recomendado

| # | Proyecto | Tamaño | Versión | Por qué en este orden |
|---|---|---|---|---|
| 1 | **Pago mixto** | Chico | 2.4 | Rápido, muy pedido y ordena los pagos de la venta, que la facturación y las devoluciones van a necesitar. |
| 2 | **Devoluciones parciales** (nota de crédito interna) | Mediano | 2.5 | La nota de crédito electrónica se monta sobre esto. Sin devoluciones no hay manera correcta de "anular" una factura electrónica. |
| 3 | **Facturación electrónica** (vía proveedor) | Grande | 3.0 | Es lo que más vende el producto. Se diseña ya con `idsucursal` (establecimiento "0000") para no rehacerla después. |
| 4 | **Multisucursal** | Grande | 3.1 | Toca todas las consultas de stock: se hace sobre una base ya estable. |

Cada fase termina con: migración idempotente, `scripts/smoke.php` ampliado, prueba en navegador, manual y `DESPLIEGUE.md` actualizados.

### Decisiones que tiene que tomar el dueño antes de empezar

1. **Proveedor de facturación por defecto** (ver §4.3). Recomendación: empezar con **un** proveedor tipo PSE/OSE con API REST y dejar la puerta abierta a otros.
2. **Modelo de venta del producto:** una instalación por cliente (como hoy, recomendado) o un servidor con muchas empresas (SaaS multiempresa, §6).
3. **¿Guía de remisión electrónica?** Solo si los clientes trasladan mercadería entre locales o a terceros (§5.6).

---

## 2. Pago mixto (v2.4)

**Problema:** una venta tiene un solo `medio_pago`. Si el cliente paga S/ 50 en efectivo y S/ 30 con Yape, hoy no se puede registrar bien, y la caja no cuadra.

### 2.1 Modelo de datos

```sql
CREATE TABLE venta_pago (
  idpago        INT AUTO_INCREMENT PRIMARY KEY,
  idventa       INT NOT NULL,
  medio_pago    VARCHAR(20) NOT NULL,      -- EFECTIVO, YAPE, PLIN, TARJETA, TRANSFERENCIA, DEPOSITO, NOTA_CREDITO
  monto         DECIMAL(11,2) NOT NULL,    -- lo que se aplica a la venta (sin vuelto)
  recibido      DECIMAL(11,2) NULL,        -- solo efectivo: lo que entregó el cliente
  num_operacion VARCHAR(40) NULL,
  idcaja        INT NULL,
  KEY (idventa)
);
```

- `venta.medio_pago` se mantiene como resumen: el medio si es uno solo, o `MIXTO` si son varios. Así los listados y reportes actuales siguen funcionando.
- La migración crea un `venta_pago` por cada venta existente (medio y total actuales).
- **Pago parcial + crédito:** la suma de pagos puede ser menor al total solo si la venta es al crédito. La diferencia genera la cuenta por cobrar (hoy la venta al crédito no admite adelanto).

### 2.2 Reglas

- La suma de pagos debe ser igual al total (contado) o menor (crédito con adelanto). Se valida en el servidor.
- El vuelto solo existe en la línea de efectivo (`recibido - monto`).
- **Caja:** un `caja_movimiento` por cada pago. El arqueo sigue contando solo EFECTIVO.
- **Anular o devolver:** se revierte por medio. Al devolver se elige el medio del reintegro (§3.3).

### 2.3 Interfaz

- En la ventana de cobro, además de los botones de medio de pago, un "Agregar otro pago" con filas `medio · monto · N° operación`. El "falta / vuelto" se recalcula en vivo.
- El ticket imprime cada pago: `Efectivo 50.00 · Yape 30.00 (op. 123456) · Vuelto 0.00`.

### 2.4 Pruebas nuevas

- Venta 80 = 50 efectivo + 30 Yape → dos movimientos de caja; el arqueo suma solo 50.
- La suma de pagos no cuadra con el total → rechazada.
- Crédito con adelanto de 20 → cuenta por cobrar de 60.

---

## 3. Devoluciones parciales y notas de crédito (v2.5)

**Problema:** solo se puede anular la venta entera. Si el cliente devuelve 1 de 5 productos, no hay registro correcto del stock, la caja ni la utilidad.

### 3.1 Conceptos

- **Devolución / nota de crédito (NC):** documento que **reduce** una venta ya emitida. Puede ser total o parcial, por ítems o por monto.
- Mientras no haya facturación electrónica es un documento **interno** (serie `NC01`). Con facturación electrónica se emite como **nota de crédito electrónica** que referencia la boleta o factura (§4).
- En facturación electrónica **no se puede "anular" una factura como hoy**: o se da de baja dentro del plazo ⚖️, o se emite una NC. Por eso esta fase va antes de la 3.0.

### 3.2 Modelo de datos

```sql
CREATE TABLE nota_credito (
  idnota        INT AUTO_INCREMENT PRIMARY KEY,
  idventa       INT NOT NULL,              -- documento que modifica
  idsucursal    INT NOT NULL DEFAULT 1,
  idusuario     INT NOT NULL,
  idautoriza    INT NULL,                  -- encargado que autorizó (misma lógica que anular)
  serie         VARCHAR(4) NOT NULL,       -- NC01 interna; FC01/BC01 electrónica
  numero        VARCHAR(8) NOT NULL,
  fecha_hora    DATETIME NOT NULL,
  tipo_nota     CHAR(2) NOT NULL,          -- catálogo SUNAT 09 (ver abajo)
  motivo        VARCHAR(200) NOT NULL,
  total         DECIMAL(11,2) NOT NULL,
  reintegro     VARCHAR(20) NOT NULL,      -- EFECTIVO, YAPE..., SALDO_A_FAVOR, REDUCE_CREDITO
  estado        VARCHAR(20) NOT NULL DEFAULT 'EMITIDA',
  UNIQUE KEY (serie, numero)
);

CREATE TABLE detalle_nota_credito (
  iddetalle        INT AUTO_INCREMENT PRIMARY KEY,
  idnota           INT NOT NULL,
  iddetalle_venta  INT NOT NULL,           -- la línea vendida que se devuelve
  idarticulo       INT NOT NULL,
  idpresentacion   INT NULL,
  idvariante       INT NULL,
  cantidad         DECIMAL(14,3) NOT NULL, -- en la presentación vendida
  factor           DECIMAL(14,3) NOT NULL,
  precio           DECIMAL(11,2) NOT NULL,
  reingresa_stock  TINYINT(1) NOT NULL DEFAULT 1  -- 0 = producto dañado: no vuelve a venta
);
```

Tipos de nota más usados (catálogo 09 de SUNAT ⚖️): `01` anulación de la operación, `06` devolución total, `07` devolución por ítem, `04` descuento global, `05` descuento por ítem, `09` disminución en el valor.

### 3.3 Reglas de negocio

| Tema | Regla |
|---|---|
| **Cantidades** | No se devuelve más de lo vendido menos lo ya devuelto en notas anteriores (por línea de venta). |
| **Stock** | Vuelve con `cantidad * factor`, a la misma talla/color (`Variante::moverStock`) y al **mismo lote** del que salió (`lote_movimiento`). Si `reingresa_stock = 0` (dañado), se registra como merma en el kardex. |
| **Dinero** | Según la venta y lo que elija el encargado: **efectivo u otro medio** (egreso de caja), **saldo a favor** (la NC se usa como medio de pago `NOTA_CREDITO` en otra venta; necesita el pago mixto) o **reduce el crédito** (baja el saldo de la cuenta por cobrar; si ya se pagó de más, reintegro). |
| **Permisos** | Igual que anular: con el permiso `anular`, o con usuario y clave de encargado (`autorizacionEncargado()`) y motivo obligatorio. Queda en auditoría. |
| **Anular completo** | "Anular" pasa a ser una NC tipo `01` por el total. Se conserva el botón, pero por dentro genera la nota (una sola lógica). |
| **Kardex y utilidad** | El kardex une ventas, compras, ajustes **y notas de crédito**. La utilidad resta el importe devuelto y el costo repuesto. |
| **Caja cerrada** | Si la caja de la venta ya se cerró, el reintegro sale de la caja abierta de quien devuelve. |

### 3.4 Interfaz

- En el detalle de una venta, botón **Devolver**: tabla con lo vendido, lo ya devuelto y el campo "a devolver" por línea, más motivo, "¿vuelve a stock?" y medio del reintegro.
- Listado de notas de crédito; PDF A4 y ticket térmico de la nota.
- En el POS: buscar la venta por número o escanear el QR del ticket para devolver rápido.

### 3.5 Devoluciones a proveedores (opcional, misma fase o después)

Nota de crédito **recibida** del proveedor: descuenta stock y reduce la cuenta por pagar. Misma estructura, espejo en compras.

---

## 4. Facturación electrónica SUNAT (v3.0)

### 4.1 Cómo funciona (explicado simple)

```
Venta en el POS
   │
   ▼
1. Se arma el comprobante en formato UBL 2.1 (XML estándar que exige SUNAT)
   │
   ▼
2. Se firma digitalmente con el CERTIFICADO DIGITAL del emisor
   │
   ▼
3. Se envía: a SUNAT directamente, o a un OSE/PSE que valida y reenvía
   │
   ▼
4. Vuelve la CDR (constancia de recepción): ACEPTADO / con OBSERVACIONES / RECHAZADO
   │
   ▼
5. Se guardan XML firmado + CDR (el emisor debe conservarlos ⚖️)
   │
   ▼
6. Se imprime la "representación impresa" (ticket o A4) con código QR y hash;
   el cliente puede consultar la validez en la web de SUNAT
```

**Documentos:** Factura (01, serie `F###`), Boleta (03, serie `B###`), Nota de crédito (07, serie que empieza con F o B según el documento que modifica), Nota de débito (08). Las series electrónicas **no necesitan autorización previa**, pero deben empezar con F o B ⚖️.

**Diferencias importantes:**

| | Factura | Boleta |
|---|---|---|
| Cliente | Obligatorio con **RUC** | Con DNI cuando el monto supera el tope que fija SUNAT ⚖️ (hoy ~S/ 700) |
| Envío | Una por una, dentro del plazo ⚖️ | Una por una o en **resumen diario** ⚖️ |
| Anular | Comunicación de baja dentro del plazo, o nota de crédito | Por resumen diario, o nota de crédito |
| Al crédito | Debe indicar **forma de pago Crédito con cuotas y fechas** ⚖️ | — |

### 4.2 Qué necesita cada empresa cliente para conectarse

1. **RUC activo** y ser **emisor electrónico** (designado por SUNAT o afiliado voluntariamente desde SOL).
2. Según el canal elegido:
   - **Vía proveedor (PSE/OSE), recomendado:** contrato con el proveedor y afiliación a él en SUNAT. El proveedor entrega un **token o credenciales de API**. Según el proveedor, el certificado digital lo pone el proveedor o el cliente.
   - **Directo a SUNAT** (SEE del contribuyente): **certificado digital** (.pfx y clave) y **usuario SOL secundario** con permisos de emisión.
3. **Datos fiscales completos** en Empresa: razón social, RUC, dirección fiscal, **ubigeo** y código de establecimiento.

En el sistema, todo esto se configura en una pantalla nueva (*Empresa → Facturación electrónica*), sin tocar código. **Así cualquier empresa puede conectarse con sus propias credenciales.**

### 4.3 Arquitectura: conectores intercambiables

```
modelos/Facturacion.php                     (orquesta: arma datos, guarda estados, reintenta)
   └── interfaz ConectorFacturacion
         ├── ConectorSimulado      (pruebas y demo: responde "ACEPTADO" sin salir a internet)
         ├── ConectorProveedorAPI  (PSE/OSE con API REST JSON: el proveedor firma y envía)
         └── ConectorSunatDirecto  (fase posterior: XML UBL + firma + web service SUNAT)
```

```php
interface ConectorFacturacion {
    public function emitir(array $comprobante): RespuestaSunat;   // factura, boleta, NC
    public function consultar(string $tipo, string $serie, string $numero): RespuestaSunat;
    public function anular(array $comprobante, string $motivo): RespuestaSunat; // baja / resumen
    public function probarConexion(): RespuestaSunat;
}
```

- **Empezar con `ConectorProveedorAPI`:** el proveedor resuelve el XML UBL, la firma, los resúmenes diarios, las bajas y los cambios de norma. El sistema solo envía JSON por `curl`. Encaja con la regla del proyecto de **no usar Composer**.
- **`ConectorSunatDirecto` queda para después:** exige generar UBL 2.1, firmar XMLDSig con OpenSSL, SOAP con SUNAT, resúmenes y bajas propios, y seguir cada cambio de norma. Es más barato por comprobante, pero mucho más trabajo y riesgo.
- El proveedor se elige por empresa (`config_facturacion.conector`). Agregar otro proveedor es escribir otro conector, no tocar ventas.

### 4.4 Modelo de datos

```sql
CREATE TABLE config_facturacion (
  idconfig        INT PRIMARY KEY DEFAULT 1,
  activa          TINYINT(1) NOT NULL DEFAULT 0,
  ambiente        VARCHAR(10) NOT NULL DEFAULT 'PRUEBAS',   -- PRUEBAS | PRODUCCION
  conector        VARCHAR(30) NOT NULL DEFAULT 'SIMULADO',
  api_url         VARCHAR(200) NULL,
  api_token       TEXT NULL,                 -- cifrado (clave en config/local.php, no en la BD)
  ubigeo          CHAR(6) NULL,
  codigo_establecimiento CHAR(4) NOT NULL DEFAULT '0000'
);

CREATE TABLE comprobante_electronico (
  idce            INT AUTO_INCREMENT PRIMARY KEY,
  origen          VARCHAR(10) NOT NULL,      -- VENTA | NOTA
  idorigen        INT NOT NULL,
  tipo            CHAR(2) NOT NULL,          -- 01, 03, 07, 08
  serie           VARCHAR(4) NOT NULL,
  numero          VARCHAR(8) NOT NULL,
  estado          VARCHAR(15) NOT NULL,      -- PENDIENTE, ENVIADO, ACEPTADO, OBSERVADO, RECHAZADO, BAJA_PENDIENTE, ANULADO
  hash            VARCHAR(100) NULL,         -- para el QR
  codigo_sunat    VARCHAR(10) NULL,
  mensaje_sunat   VARCHAR(500) NULL,
  xml_ruta        VARCHAR(200) NULL,         -- files/fe/{ruc}/{año}/{mes}/ (fuera de la web)
  cdr_ruta        VARCHAR(200) NULL,
  pdf_url         VARCHAR(300) NULL,         -- si el proveedor lo genera
  intentos        INT NOT NULL DEFAULT 0,
  proximo_intento DATETIME NULL,
  UNIQUE KEY (tipo, serie, numero)
);
```

**Cambios en tablas existentes:**

| Tabla | Cambio | Motivo |
|---|---|---|
| `articulo` | `tipo_afectacion_igv` (10 gravado, 20 exonerado, 30 inafecto ⚖️) | El IGV depende del producto, no del comprobante. |
| `unidad_medida` | `codigo_sunat` (catálogo 03: `NIU`, `KGM`, `MTR`, `ZZ`…) | Obligatorio en cada línea. |
| `persona` | Tipo de documento según catálogo 06 (1 DNI, 6 RUC, 4 CE, 0 sin documento) | Validación de factura (RUC) y de boleta por monto. |
| `venta` | `forma_pago` + tabla `venta_cuota` para facturas al crédito | Requisito de SUNAT para facturas al crédito ⚖️. |
| `configuracion_empresa` | Bolsas plásticas (ICBPER) si el rubro lo usa ⚖️ | Bodegas y minimarkets. |

### 4.5 Cosas del sistema actual que hay que corregir antes (encontradas en el código)

1. ✅ **(v2.3.1) La boleta ya lleva IGV:** el servidor fija el IGV de la empresa en boleta y factura, y 0 en la nota de venta. ⬜ Pendiente para la 3.0: calcularlo **por línea** según `tipo_afectacion_igv` (productos exonerados o inafectos).
2. ✅ **(v2.3.1) IGV guardado como fracción corregido:** la migración `20260922_igv_correlativo.sql` pasó 3 ventas y 1 compra de 2021 de `0.18` a `18`. Empresa y compras ya rechazan valores entre 0 y 1.
3. ✅ **(v2.3.1) Numeración automática:** el servidor ignora cualquier número enviado y asigna el siguiente correlativo con bloqueo; el campo es de solo lectura. Boletas y facturas ya no se eliminan (solo se anulan) para no dejar huecos. Quedan anomalías **históricas** del sistema anterior (una Factura `001` repetida y saltos en la B001 por números escritos a mano en 2021) que no se tocan porque son documentos ya entregados: **al activar la facturación electrónica conviene estrenar series nuevas** (por ejemplo B002 y F002). Nota: `scripts/smoke.php` crea y borra boletas de prueba con SQL; con facturación electrónica debe usar el conector simulado y una serie de pruebas.
4. **"Ticket" no es comprobante tributario:** se renombra a **Nota de venta** (uso interno) para que nadie lo confunda con una boleta.
5. **Anular:** para comprobantes electrónicos, el botón Anular genera la baja o la NC (§3), nunca un simple cambio de estado.

### 4.6 Flujo en el POS

1. El vendedor cobra como hoy. Al guardar, la venta queda registrada **y** su comprobante en estado `PENDIENTE`.
2. Se envía de inmediato (en segundo plano). El ticket se imprime con el QR y el hash, y la leyenda "Representación impresa de la boleta electrónica".
3. **Sin internet o con SUNAT caída:** la venta **no se bloquea**. Queda `PENDIENTE` y una **cola de reintentos** la envía después (tarea programada en Windows o cron, más reintento al abrir el sistema), siempre dentro del plazo ⚖️.
4. **Panel "Comprobantes electrónicos":** estados, filtros (rechazados, pendientes), reenviar, descargar XML y CDR, y una alerta en la campana si algo lleva más de X horas sin aceptarse.
5. **Rechazado:** se muestra el mensaje de SUNAT traducido y se corrige (por ejemplo, un RUC inválido del cliente) antes de reenviar.

### 4.7 Validaciones antes de enviar

- Factura: cliente con RUC de 11 dígitos (opcional: consulta de RUC o DNI a un servicio externo para autocompletar).
- Boleta por encima del tope: DNI obligatorio ⚖️.
- Totales cuadrados al céntimo (gravado + IGV + exonerado + inafecto + ICBPER = total).
- Fecha de emisión dentro del plazo permitido ⚖️.

### 4.8 Pruebas

- `ConectorSimulado` en `smoke.php`: emitir boleta, factura y NC; rechazo simulado; reintento.
- Ambiente de **pruebas** del proveedor o **beta de SUNAT** antes de pasar a producción.
- Checklist de puesta en marcha por cliente: probar la conexión, emitir una boleta de prueba, validarla en la consulta de SUNAT y recién entonces cambiar a PRODUCCIÓN.

---

## 5. Multisucursal (v3.1)

**Objetivo:** una misma empresa (un RUC) con varios locales en **una sola instalación**. Cada local tiene su stock, su caja, sus series y sus vendedores. El dueño ve todo junto o por local.

### 5.1 Modelo de datos

```sql
CREATE TABLE sucursal (
  idsucursal     INT AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(80) NOT NULL,
  direccion      VARCHAR(180) NULL,
  ubigeo         CHAR(6) NULL,
  codigo_sunat   CHAR(4) NOT NULL DEFAULT '0000',   -- código de establecimiento anexo
  telefono       VARCHAR(30) NULL,
  condicion      TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE stock_sucursal (
  idarticulo     INT NOT NULL,
  idvariante     INT NULL,
  idsucursal     INT NOT NULL,
  stock          DECIMAL(14,3) NOT NULL DEFAULT 0,
  stock_minimo   DECIMAL(14,3) NOT NULL DEFAULT 0,
  UNIQUE KEY (idarticulo, idvariante, idsucursal)
);

CREATE TABLE serie_documento (
  idserie        INT AUTO_INCREMENT PRIMARY KEY,
  idsucursal     INT NOT NULL,
  tipo           VARCHAR(20) NOT NULL,     -- Boleta, Factura, NotaCredito, NotaVenta, Cotizacion
  serie          VARCHAR(4) NOT NULL,      -- B001 local 1, B002 local 2...
  UNIQUE KEY (tipo, serie)
);

CREATE TABLE usuario_sucursal (idusuario INT NOT NULL, idsucursal INT NOT NULL, PRIMARY KEY (idusuario, idsucursal));
```

Se agrega `idsucursal` a: `venta`, `ingreso`, `ajuste_inventario`, `caja_diaria`, `cotizacion`, `nota_credito`, `lote`.

### 5.2 Stock

- `articulo.stock` se conserva como **total de todas las sucursales**, para que reportes y alertas globales sigan funcionando. El stock que se vende y controla es el de `stock_sucursal`.
- **Triggers:** hoy mueven `articulo` y `articulo_variante`. Deben mover también `stock_sucursal` de la sucursal de la cabecera. Opción recomendada: **pasar el movimiento de stock de los triggers a un solo método PHP** (`Stock::mover($idarticulo, $idvariante, $idsucursal, $cantidad, $ref)`). Hoy la lógica está repartida entre triggers, `Variante::moverStock` y `Lote`, y con sucursales eso se vuelve inmanejable. Es el cambio más delicado de todo el plan: la prueba de humo tiene que cubrir venta, compra, ajuste, anulación, devolución, lotes y variantes **por sucursal**.
- **Lotes:** cada lote pertenece a una sucursal. El FEFO se calcula dentro de la sucursal.

### 5.3 Sesión y permisos

- Cada usuario tiene una o varias sucursales (`usuario_sucursal`). Al iniciar sesión trabaja en **su** sucursal. Si tiene varias, elige una en la cabecera.
- Permiso nuevo **"Ver todas las sucursales"** (dueño o gerente): reportes consolidados y cambio de sucursal.
- El vendedor solo ve el stock y las ventas de su local. El POS muestra el stock de su sucursal y, opcionalmente, "hay 5 en el local 2".

### 5.4 Caja y series

- Una caja por usuario **en** su sucursal. El arqueo y el historial se filtran por sucursal.
- Cada sucursal tiene sus series (B001, F001 en el local 1; B002, F002 en el local 2). El correlativo es por serie, como hoy.
- En facturación electrónica cada comprobante lleva el `codigo_sunat` del establecimiento de su sucursal.

### 5.5 Transferencias entre sucursales

Documento nuevo **Transferencia**: origen, destino, artículos, cantidades, lote o talla. Estados: `ENVIADA` (sale del origen) → `RECIBIDA` (entra al destino, con confirmación y diferencias si llegó menos). Queda en el kardex de ambas sucursales.

### 5.6 Guía de remisión electrónica (opcional)

Trasladar mercadería entre locales de la misma empresa, o hacia clientes, puede requerir **guía de remisión remitente electrónica** ⚖️ (motivo "traslado entre establecimientos de la misma empresa"). Se emitiría con el mismo conector de §4.3. Se decide según los clientes: muchas bodegas no la necesitan; distribuidoras sí.

### 5.7 Migración de una instalación existente

1. Crear la sucursal "Principal" (código `0000`) con los datos de la empresa.
2. `stock_sucursal` = stock actual de cada artículo y variante en la Principal.
3. Todos los documentos, cajas, lotes y series existentes → `idsucursal = 1`. Todos los usuarios → Principal.
4. Mientras exista una sola sucursal, **la interfaz no cambia**: el selector de sucursal aparece recién con la segunda.

### 5.8 Reportes

Todos los reportes (ventas, utilidad, kardex, stock valorizado, caja) con un filtro **Sucursal** (o "Todas" para quien tenga el permiso). En el escritorio: comparativo de ventas por local.

---

## 6. ¿Y "cualquier empresa" en un solo servidor? (SaaS multiempresa)

Hay dos formas de atender a muchas empresas:

| | **A. Una instalación por cliente** (recomendado ahora) | **B. Un servidor para todos (SaaS)** |
|---|---|---|
| Cómo | Mismo código, una base de datos por empresa (local o en tu hosting, un subdominio por cliente) | Una base con `idempresa` en **todas** las tablas |
| Trabajo | Ya funciona así | Rehacer todas las consultas y probar aislamiento entre empresas |
| Riesgo | Bajo: si falla un cliente, no afecta a otros | Alto: un error de filtro muestra datos de otra empresa |
| Actualizar | Hay que actualizar cada instalación (se puede automatizar con un script) | Una sola vez |

**Recomendación:** seguir con **A** y automatizar la creación y actualización de instalaciones (script que clona, crea la BD, corre `migrar.php` y `smoke.php`). La facturación electrónica ya queda "para cualquier empresa" porque cada instalación configura su RUC, su proveedor y sus credenciales. Pasar a **B** solo cuando haya muchos clientes y el costo de mantener instalaciones separadas lo justifique.

---

## 7. Riesgos y cómo mitigarlos

| Riesgo | Mitigación |
|---|---|
| Cambian las normas de SUNAT (plazos, catálogos, montos) | Usar un proveedor que absorba los cambios; concentrar las reglas ⚖️ en un solo archivo de configuración; revisarlo con el contador del cliente. |
| Comprobantes rechazados sin que nadie se entere | Panel de estados, alerta en la campana y resumen diario del administrador. |
| Internet caído en el local | Emitir igual y enviar desde la cola dentro del plazo; avisar si se acerca el límite. |
| Romper el stock al pasar a multisucursal | Centralizar el movimiento en `Stock::mover()`, probar cada operación por sucursal en `smoke.php` y migrar con respaldo previo y verificación de que la suma por sucursal = stock anterior. |
| Credenciales del proveedor expuestas | Token cifrado en la BD con clave en `config/local.php`; XML y CDR fuera de la carpeta pública; solo el administrador ve la configuración. |
| Costo del proveedor por comprobante | Definir si lo paga el cliente directo al proveedor (recomendado) o se incluye en el precio del sistema. |

---

## 8. Checklist por fase

### v2.4 Pago mixto
- [ ] Migración `venta_pago` + datos existentes
- [ ] Cobro con varias líneas de pago, vuelto solo en efectivo, adelanto en crédito
- [ ] Caja: un movimiento por medio; el arqueo cuenta solo efectivo
- [ ] Ticket, PDF y detalle muestran cada pago
- [ ] Anulación revierte por medio
- [ ] Smoke y manual

### v2.5 Devoluciones y notas de crédito
- [ ] Tablas `nota_credito` y `detalle_nota_credito`
- [ ] Devolución por ítem con tope por lo ya devuelto
- [ ] Stock a la misma talla y lote; opción "dañado, no vuelve a stock"
- [ ] Reintegro: medio de pago, saldo a favor o reduce el crédito
- [ ] Autorización de encargado, motivo y auditoría
- [ ] Kardex, utilidad y reportes incluyen las NC
- [ ] "Anular" pasa a generar una NC por el total
- [ ] PDF y ticket de la NC; smoke y manual

### v3.0 Facturación electrónica
- [ ] Corregir IGV por producto, normalizar `0.18`, bloquear número manual y renombrar Ticket a Nota de venta
- [ ] Catálogos SUNAT en unidades, documentos y afectación
- [ ] `config_facturacion` + pantalla de configuración + "probar conexión"
- [ ] Conector simulado + conector del proveedor elegido
- [ ] Emisión de boleta, factura y NC; baja y resumen según el proveedor
- [ ] Cola de reintentos y panel de estados
- [ ] Representación impresa con QR y hash (ticket y A4)
- [ ] Factura al crédito con cuotas
- [ ] Pruebas en ambiente de pruebas y checklist de puesta en marcha por cliente

### v3.1 Multisucursal
- [ ] Tablas `sucursal`, `stock_sucursal`, `serie_documento`, `usuario_sucursal` e `idsucursal` en documentos
- [ ] `Stock::mover()` único (reemplaza la lógica repartida en triggers)
- [ ] Sesión con sucursal activa y permiso "Ver todas las sucursales"
- [ ] Caja, series y lotes por sucursal
- [ ] Transferencias entre sucursales
- [ ] Reportes con filtro de sucursal y consolidado
- [ ] Migración a "Principal" con verificación de stock; smoke por sucursal
- [ ] (Opcional) Guía de remisión electrónica
