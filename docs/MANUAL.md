# Manual de uso — Mi Tienda

Guía práctica para quien atiende el mostrador y para quien administra la tienda.
No hace falta saber de informática: cada sección explica **qué hace el sistema** y
**qué tienes que hacer tú**.

- [Primeros pasos](#primeros-pasos)
- [Elegir el tipo de negocio](#elegir-el-tipo-de-negocio)
- [Ferretería: fracciones, empaques y precio por mayor](#ferretería-fracciones-empaques-y-precio-por-mayor)
- [Abarrotes: lotes y fechas de vencimiento](#abarrotes-lotes-y-fechas-de-vencimiento)
- [Ropa: tallas y colores](#ropa-tallas-y-colores)
- [Día a día: vender, comprar, corregir](#día-a-día-vender-comprar-corregir)
- [Varios almacenes (tienda, depósito, otro local)](#varios-almacenes-tienda-depósito-otro-local)
- [Anular o eliminar un documento](#anular-o-eliminar-un-documento)
- [Preguntas frecuentes](#preguntas-frecuentes)

---

## Primeros pasos

1. **Entra** con el usuario que te dieron. Cambia tu contraseña desde *Mi perfil*.
2. **Configura tu empresa**: menú *Configuración → Empresa y marca*. Pon el nombre,
   el logo, los colores, las series de tus comprobantes, el impuesto y la moneda.
   Eso aparece en el panel, en los tickets y en los PDF.
3. **Elige el tipo de negocio** (ver la sección siguiente).
4. **Carga tus productos**: *Almacén → Artículos*, uno por uno, o
   *Almacén → Importar desde Excel* si ya los tienes en una hoja de cálculo.
5. **Abre tu caja** del día en *Finanzas → Caja diaria* antes de empezar a vender.

---

## Elegir el tipo de negocio

En *Configuración → Empresa y marca* hay una sección **Tipo de negocio**. Elige el
que se parezca al tuyo y guarda:

| Si tu negocio es… | El sistema habilita |
| --- | --- |
| **General** | Inventario estándar, sin funciones de rubro |
| **Abarrotes / bodega / minimarket** | Fechas de vencimiento, lotes y venta por fracción |
| **Ferretería / materiales** | Venta por fracción, empaques (caja, rollo) y precio por mayor |
| **Ropa / calzado / boutique** | Tallas y colores con stock propio, temporada y colección |

Cambiar de tipo **no borra nada**: solo muestra u oculta campos. Si te equivocas,
vuelve a cambiarlo y todo sigue ahí.

---

## Ferretería: fracciones, empaques y precio por mayor

### Vender 1.5 metros (fracciones)

El sistema permite decimales solo en las unidades que tienen sentido. Ve a
*Almacén → Unidades de medida*, edita la unidad (por ejemplo **Metro**) y marca
**"Permite cantidades con decimales"**. Metro, kilo, litro y galón ya vienen
marcados; unidad, caja y paquete no, porque no se vende media unidad.

Desde ese momento, en la venta puedes escribir `1.5` en la cantidad de ese artículo.

### Vender o comprar por caja, rollo o paquete (empaques)

En la ficha del artículo, sección **Presentaciones y empaques**, agrega por ejemplo:

| Nombre | Contiene | Precio venta | Precio compra | Código de barras |
| --- | --- | --- | --- | --- |
| Rollo x50 | 50 | 110.00 | 90.00 | 7751234 |

Qué cambia al vender o comprar:

- En la fila aparece un selector: **metros sueltos** o **Rollo x50**.
- Escribes **1 rollo** y el sistema descuenta **50 metros** del stock.
- Si el rollo tiene su propio código de barras, al escanearlo entra como rollo.
- El ticket dice "1 Rollo x50", no "50 m".

El stock siempre se lleva en la unidad base (metros). Solo cambia la forma de vender.

### Precio por mayor

En la ficha del artículo, sección **Precio por mayor**, agrega escalas:

| Desde | Precio unitario |
| --- | --- |
| 20 | 2.20 |
| 50 | 2.00 |

En la venta, cuando el cajero escribe 25, el precio baja solo a 2.20. Si escribe 60,
baja a 2.00. **El cajero siempre puede cambiar el precio a mano**: si lo escribe, el
sistema respeta lo que escribió.

---

## Abarrotes: lotes y fechas de vencimiento

### Registrar el vencimiento al comprar

Al registrar la compra, cada fila tiene dos campos: **Lote** y **Vence**. Llénalos y
el sistema crea un lote con esa mercadería. Si los dejas vacíos, la mercadería entra
como stock normal, sin control de vencimiento.

También puedes crear un lote desde *Ajustes de inventario → Entrada* (útil para
mercadería que ya tenías).

### Sale primero lo que vence primero

No tienes que hacer nada: al vender, el sistema descuenta del lote que vence antes.
En el punto de venta, bajo el nombre del producto, verás un aviso como
**"vence 21/09/2026"** en amarillo o rojo según lo cerca que esté.

### Lo vencido no se vende

Si un lote ya venció, ese stock deja de estar disponible. La pantalla lo avisa
("5 vencido") y el sistema no deja venderlo, aunque el total del artículo alcance.

### Dar de baja lo vencido

Ve a *Inventario → Vencimientos*. Verás tres tarjetas (vencidos, por vencer, lotes con
stock) y la lista de lotes. El botón de papelera **da de baja el lote completo**: el
stock se descuenta y queda registrado como ajuste con motivo "Producto vencido", con
tu nombre y la fecha.

### Avisos automáticos

La campana del panel y el escritorio avisan de lotes vencidos y por vencer. Los días
de anticipación se configuran en *Configuración → Empresa y marca* ("Avisar vencimientos con
N días de anticipación", 30 por defecto).

---

## Ropa: tallas y colores

### Cargar un modelo con sus tallas

En la ficha del artículo, sección **Tallas y colores**:

1. Escribe las tallas separadas por comas: `S, M, L, XL`.
2. Escribe los colores: `Negro, Blanco`.
3. Pulsa **Generar combinaciones**. El sistema crea las 8 combinaciones.
4. Escribe el **stock inicial** de cada una y, si quieres, su código de barras.

Cada combinación puede tener su propio precio. Si lo dejas en 0, usa el precio del
artículo.

El **stock del artículo es la suma de sus tallas** y no se escribe a mano.

### Vender

En la venta, cada fila tiene un selector de talla/color que muestra el stock de cada
una; las agotadas aparecen deshabilitadas. Si la combinación tiene código de barras,
al escanearlo entra directo con esa talla.

El tope es por combinación: si hay 7 en M/Negro, no deja vender 8, aunque el modelo
tenga 20 en total.

### Cargar muchos modelos desde Excel

En *Almacén → Importar desde Excel*, la plantilla incluye las columnas **Talla** y
**Color**. Pon una fila por combinación, repitiendo el nombre del modelo:

| Nombre | Código | Categoría | Stock | Precio venta | Talla | Color |
| --- | --- | --- | --- | --- | --- | --- |
| Casaca urbana | CAS-S-AZ | Ropa | 4 | 120 | S | Azul |
| Casaca urbana | CAS-M-AZ | | 6 | 120 | M | Azul |
| Casaca urbana | CAS-L-AZ | | 2 | 135 | L | Azul |

La categoría basta ponerla en una fila. El código de cada fila es el de esa
combinación.

### Etiquetas

*Almacén → Etiquetas de código de barras* imprime una etiqueta por combinación, con su propio código de
barras y precio.

---

## Día a día: vender, comprar, corregir

### Vender (punto de venta)

1. *Ventas → Punto de venta* y pulsa **Abrir punto de venta** (o `Alt+V` desde cualquier
   pantalla). El punto de venta ocupa toda la pantalla; el botón de las flechas lo pone
   además en pantalla completa del navegador.
2. Toca los productos en la cuadrícula (puedes filtrar por categoría) o escanea el código.
   Al escribir en el buscador la cuadrícula se filtra; con `Enter` se agrega el producto.
3. Elige la presentación o la talla si el artículo las tiene. Ajusta la cantidad con
   `−` / `+` (o las teclas `+` y `-` para el último producto agregado).
4. Elige el cliente y el comprobante (Boleta, Factura o Ticket). La serie, el número y
   la fecha están en el botón de ajustes, al lado de "Ticket".
5. **Cobrar** (`F4`): elige contado o crédito y el medio de pago. En efectivo escribe lo
   que te entregan (o usa los botones de billetes) y el sistema calcula el **vuelto**.
   Con Yape, Plin, tarjeta o depósito puedes anotar el número de operación.
6. `Enter` confirma. El ticket se imprime solo y el punto de venta queda listo para la
   siguiente venta. Arriba del carrito ves la última venta con botones para
   reimprimir el ticket o sacar el PDF A4.

Si vendes al crédito, el sistema crea sola la **cuenta por cobrar**. Si vendes al
contado con la caja abierta, registra solo el **ingreso en caja**.

**Pagar con varios medios (pago mixto).** En la ventana de cobro, **Pagar con varios
medios** abre una lista: por ejemplo S/ 20 en efectivo y S/ 15 por Yape. **Agregar otro
medio** suma una línea; abajo ves lo **pagado**, lo que **falta** y el **vuelto**. El
vuelto solo sale del efectivo: si el cliente te da S/ 50 para los S/ 20 en efectivo, el
vuelto es S/ 30. En la caja entra un movimiento por cada medio, y el arqueo cuenta solo
el efectivo. El ticket, el PDF y el detalle de la venta muestran cada pago.

**Crédito con adelanto.** Elige *Crédito* y marca **Registrar un adelanto**: lo que el
cliente paga hoy entra a la caja por su medio y la cuenta por cobrar queda solo por el
saldo.

**Saldo a favor.** Si el cliente tiene saldo a favor por una devolución (ver más abajo),
al cobrarle aparece el aviso *"El cliente tiene S/ … de saldo a favor"* con el botón
**Usar**: se descuenta de la venta y, si no alcanza, el resto queda en efectivo (puedes
cambiar el medio).

### Comprar

*Compras → Ingresos / Compras* y **Nueva compra** (o `Alt+C`). La compra también ocupa
toda la pantalla:

1. Elige el proveedor y los datos de su comprobante.
2. En el buscador (`F3`) escanea o escribe el nombre: aparece la lista con stock y
   último costo; elige con las flechas y `Enter`. También puedes abrir el catálogo (`F2`).
3. Escribe la cantidad y pulsa `Enter` para pasar al precio de compra, otra vez `Enter`
   para el precio de venta y otra para volver al buscador.
4. En **¿Cómo pagaste?** elige Efectivo, Depósito, Transferencia, Yape, Plin o Tarjeta.
   Si no fue en efectivo, anota la cuenta o banco del proveedor y el N° de operación.
   Al crédito, indica la fecha de vencimiento.
5. `F4` registra la compra. El stock sube y los precios de referencia se actualizan.

**Autoguardado:** mientras armas la compra, se guarda sola en esa PC ("Guardado 20:31"
arriba). Si cierras la pestaña, se corta la luz o sales al listado, al volver aparece
"Tienes una compra sin terminar" con el botón **Continuar**. El borrador se borra al
registrar la compra o con **Descartar**.

### Usuarios y roles

En *Configuración → Usuarios* pulsa **Nuevo usuario** y, en **Rol**, elige una tarjeta.
Marca de golpe los permisos del puesto; después puedes quitar o agregar permisos uno
por uno (cada permiso explica qué habilita).

| Rol | Qué puede hacer |
| --- | --- |
| **Vendedor / cajero** | Solo el punto de venta y su propia caja. Ve únicamente sus ventas, vende al precio de lista sin descuentos, anula solo con la clave de un encargado y no ve costos, compras ni configuración. Al entrar va directo al punto de venta. |
| **Encargado de tienda** | Vende, compra, ajusta stock, cambia precios, anula y autoriza anulaciones, ve las ventas de todos, cuentas y reportes. No administra usuarios. |
| **Almacenero** | Artículos, ajustes de inventario, vencimientos y kardex. |
| **Compras** | Compras y proveedores, pagos a proveedores y reportes de compras. |
| **Administrador** | Todo el sistema. |

- Si quitas un permiso o desactivas a un usuario, el cambio se aplica **de inmediato**,
  aunque la persona tenga el sistema abierto.
- **Anular documentos** es un permiso aparte: dáselo solo a quien controle las ventas.
- **Consulta ventas** permite ver las ventas de todos los vendedores. Sin él, cada
  vendedor ve solo las suyas (listado, detalle, ticket y PDF).
- **Cambiar precios y descuentos**: sin este permiso el precio y el descuento del punto
  de venta y de las cotizaciones quedan bloqueados en el precio de lista (con caja, talla
  o precio por mayor ya aplicados). Si un encargado hizo una cotización con precio
  especial, el vendedor sí puede convertirla en venta con ese precio.

### Anular con autorización

Quien no tiene "Anular documentos" también ve el botón **Anular** (en el listado de
ventas y en la última venta del punto de venta), pero se abre una ventana que pide el
**motivo** y el **usuario y la clave de un encargado** (alguien con "Anular documentos"
o administrador). En *Auditoría* queda quién pidió la anulación, quién la autorizó y el
motivo. Si se escribe mal la clave del encargado varias veces, esa cuenta se bloquea unos
minutos, igual que en el inicio de sesión.

### Devoluciones y notas de crédito

Cuando el cliente devuelve **parte** de lo que compró (o todo), no anules la venta:
registra una **devolución**. Queda una **nota de crédito** (serie NC01) ligada a la
venta.

1. En *Ventas*, abre el detalle de la venta y pulsa **Devolver**.
2. En cada producto escribe cuánto vuelve (o **Todo**). No deja devolver más de lo
   vendido ni lo que ya se devolvió antes.
3. **¿Vuelve a stock?** Desmárcalo si el producto viene dañado: no regresa a la venta
   (queda anotado en la nota de crédito).
4. Elige el **motivo** y **cómo se devuelve el dinero**: efectivo (sale de la caja),
   Yape, Plin, extorno a tarjeta, transferencia, depósito o **saldo a favor** del
   cliente para otra compra.
5. **Registrar devolución**. Se imprime el comprobante de la devolución.

Qué hace el sistema:

- El stock vuelve **a la misma talla/color, al mismo lote y al mismo almacén** de la venta.
- Si la venta fue **al crédito**, primero se descuenta la deuda del cliente; solo lo que
  sobre se le devuelve.
- Quien no tiene "Anular documentos" necesita el **usuario y la clave de un encargado**.
  Todo queda en *Auditoría* con el motivo.
- Las devoluciones restan en el kardex, en la utilidad y en los reportes de ventas.
- En *Ventas → Devoluciones (notas de crédito)* ves todas las notas, con su PDF y su
  ticket.

**Anular una venta genera una nota de crédito por el total** y devuelve el dinero por el
mismo medio con que se cobró. Una venta que ya tiene devoluciones parciales no se anula:
se devuelve el resto.

### La caja del vendedor

- Un vendedor **no puede cobrar con la caja cerrada**. En el punto de venta, arriba,
  ve el estado de su caja; si está cerrada, el botón **Abrir caja** la abre ahí mismo
  (también se ofrece al intentar cobrar). El administrador puede vender sin caja.
- Para cerrar: botón **Cerrar caja** (arriba en el punto de venta) → *Caja diaria* →
  **Cerrar caja**. Cuenta **solo los billetes y monedas del cajón** y escríbelo.
- **Arqueo ciego** (activado por defecto en *Empresa y marca → Caja*): quien no es
  administrador no ve cuánto debería haber ni la diferencia; solo cuenta y escribe lo
  que tiene. El administrador ve el cuadre de cada caja en *Caja diaria → Historial*
  marcando "Ver cajas de todos los usuarios".
- El **efectivo esperado** suma solo lo cobrado y pagado en efectivo. Lo cobrado por
  Yape, Plin, tarjeta, transferencia o depósito aparece aparte ("otros medios"), porque
  no está en el cajón.

### Ticketera (impresora de tickets)

En *Configuración → Empresa y marca → Ticket e impresora* eliges:

- **Ancho del papel**: 80 mm (la más común) o 58 mm.
- **Copias por venta** (1 a 3) y si se muestra el **logo**.
- **Texto bajo el nombre** (horario, redes) y **mensaje al pie**.
- **Imprimir solo al cobrar**: el punto de venta imprime el ticket sin preguntar. Cada
  caja puede desactivarlo en la ventana de cobro (se recuerda en esa PC).

Con **Ver ticket de prueba** revisas cómo sale antes de vender.

El ticket lleva un **código QR**: el cliente lo escanea con su celular y ve solo esa
compra, la imprime o descarga el PDF. Para que funcione fuera de la tienda escribe la
**Dirección pública** del sistema (tu dominio, ej. `https://mitienda.pe`) en la misma
sección. También puedes cambiar el **aviso de canje** por boleta o factura
electrónica SUNAT que sale al pie.

Para que salga directo y abra el cajón:

1. Instala la ticketera y márcala como **impresora predeterminada** de Windows, con
   papel de 80 o 58 mm y márgenes en cero.
2. El **cajón de dinero** se abre desde el controlador de la ticketera: en
   *Preferencias de impresión* activa "Abrir cajón" (Cash drawer / Kick drawer). El
   navegador no puede abrir el cajón por sí solo; lo hace la impresora al imprimir.
3. Para que no aparezca la ventana de impresión, crea un acceso directo de Chrome o Edge
   en la PC de caja con la opción `--kiosk-printing`, por ejemplo:
   `"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing http://localhost/mi_tienda`.
   Sin esa opción el navegador muestra la vista previa y basta con pulsar `Enter`.

### Corregir el stock

*Inventario → Ajustes de inventario*, con **Entrada** o **Salida** y un motivo
(conteo, merma, vencimiento, robo, uso interno…). Todo ajuste queda en el kardex con
tu nombre. Es la forma correcta de corregir: no edites el stock a mano en la ficha
del artículo si puedes evitarlo.

- **Con el lector**: en el campo *Escanear código* lee el código del producto, de la
  caja (suma lo que trae la caja), de la talla/color o del lote. Cada lectura del mismo
  producto suma a la cantidad.
- **Lotes** (abarrotes, farmacia): en una salida eliges de qué lote sale (o automático:
  primero lo vencido). En una entrada eliges *Sin lote*, *Lote nuevo* (código y
  vencimiento) o *Sumar a* un lote que ya existe.

### Toma de inventario (contar todo el almacén)

*Inventario → Toma de inventario*. Sirve para el inventario físico: cuentas con el
lector y el sistema te dice qué falta y qué sobra, en unidades y en soles.

1. **Empezar**: elige *Todo el almacén* o una categoría (un pasillo es más fácil de
   terminar en un día). Si tu rubro usa lotes, deja marcado *Contar cada lote por
   separado*.
2. **Contar**: escanea cada unidad. Para varias iguales escribe la cantidad antes de
   escanear, o usa `12*código`. Una caja con su propio código suma su equivalencia.
   - Si el producto tiene **tallas**, escanea el código de la talla o elige la talla.
   - Si tiene **lotes**, elige el lote (con la tecla del número). Si encontraste un lote
     que el sistema no tiene, regístralo con su código y vencimiento. Con *Usar este
     lote en las próximas lecturas* no te vuelve a preguntar.
   - Un código que el sistema no conoce abre la búsqueda por nombre.
   - Pueden contar varias personas a la vez, cada una en su PC o celular.
   - ¿Te equivocaste? Corrige el número en la tabla o quita la línea.
3. **Pendientes**: lista lo que tiene stock y nadie contó. Cuéntalo o marca *No hay*.
4. **Revisar y aplicar**: muestra cada ajuste que se hará y su valor. Marca *Poner en
   cero lo que no se contó* solo si contaste todo el alcance. Al aplicar se generan los
   ajustes (motivo *Conteo físico*), el conteo se cierra y se abre el **reporte** para
   imprimir y firmar.

Si se vende mientras cuentas no se descuadra: el sistema ajusta solo la diferencia
entre lo que contaste y lo que había en ese momento. Solo puede haber un conteo abierto
a la vez. Aplicar o anular un conteo requiere el permiso *Ajustes de inventario*.

### El lector de código de barras

Cualquier lector USB funciona: se comporta como un teclado. El sistema reconoce la
lectura aunque el lector no envíe `Enter` al final (o envíe `Tab`), en el punto de
venta, compras, cotizaciones, ajustes y toma de inventario. Si no lee nada, revisa en
su manual que esté en modo *USB HID / teclado* y con el idioma de teclado correcto.

---

## Varios almacenes (tienda, depósito, otro local)

Si tienes mercadería en más de un lugar (la tienda y un depósito, o dos locales), cada
**almacén lleva su propio stock**. El stock total del artículo es la suma de todos.

### Crear almacenes

*Almacén → Almacenes y transferencias* → **+ Almacén** (necesitas el permiso
"Almacenes"; el administrador lo tiene). Escribe el nombre, la dirección y el
responsable. Uno es el **principal** (la estrella lo cambia); ahí queda todo lo que ya
tenías. Un almacén que ya no usas se **desactiva**.

Mientras tengas un solo almacén, el sistema funciona igual que siempre y no muestra nada
de esto.

### En qué almacén trabajas

Con dos o más almacenes, arriba a la derecha aparece el **almacén donde trabajas**
(también en el punto de venta, junto a la caja). Todo lo que haces sale o entra ahí:

- **Ventas**: se vende y se descuenta del almacén de trabajo. Si ahí no alcanza, el
  sistema avisa cuánto hay en otros almacenes.
- **Compras**: el campo **Entra a** dice a qué almacén llega la mercadería (quien tiene
  el permiso "Almacenes" puede elegir otro).
- **Ajustes y toma de inventario**: se ajusta y se cuenta el stock de ese almacén.
- **Devoluciones**: vuelven al almacén de la venta.

Quien tiene el permiso "Almacenes" cambia de almacén desde ese menú. A cada usuario se le
asigna su almacén en *Usuarios* (**Almacén donde trabaja**): al entrar, trabaja ahí.

### Mover mercadería entre almacenes (transferencias)

1. **Nueva transferencia**: elige **origen** y **destino**.
2. Escanea los productos o búscalos por nombre; indica cuánto envías. Con tallas o lotes,
   elige cuál (si no eliges lote, salen primero los que vencen antes).
3. **Enviar**. La mercadería sale del origen y queda **en tránsito** hasta que llega.
4. En el destino abren la transferencia (**Ver**) y pulsan **Recibir**, anotando lo que
   llegó. Si llegó menos, la diferencia se da de baja como faltante.

Si el destino está en el mismo local, marca **Llega al instante (recibir ahora)** y se
recibe en el acto. Una transferencia que aún no se recibe se puede **anular**: todo
vuelve al origen.

### Ver el stock de cada almacén

- *Almacenes y transferencias → Stock por almacén*: cada artículo con lo que hay en cada
  almacén y en tránsito.
- *Kardex*: el filtro **Almacén** muestra los movimientos de un solo almacén (con los
  traslados como entrada o salida). Con "Todos" los traslados no aparecen, porque solo
  cambian la mercadería de lugar.
- *Vencimientos*: cada lote dice en qué almacén está.

## Anular o eliminar un documento

Son cosas distintas:

| | **Anular** | **Eliminar** |
| --- | --- | --- |
| Qué pasa | El documento queda registrado como *Anulado* | Desaparece por completo |
| Quién puede | Quien tenga el permiso "Anular documentos" (o con la clave de un encargado) | Solo el administrador |
| El stock | Vuelve como estaba | Vuelve como estaba (si seguía vigente) |
| El dinero (ventas) | Se emite una nota de crédito y se devuelve por el mismo medio | Se borran sus cobros |
| Cuándo usarlo | Lo normal: te equivocaste y quieres dejar constancia | Nota de venta de prueba o compra mal registrada |

**Las boletas y facturas no se eliminan, solo se anulan.** Su numeración tiene que ser
correlativa y sin huecos (lo exige SUNAT), y borrar una dejaría un salto o haría que la
siguiente repita su número. Por la misma razón, el **número del comprobante lo pone
siempre el sistema**: no se puede escribir a mano. Solo la **nota de venta** (Ticket,
documento interno) se puede eliminar.

**IGV:** la boleta y la factura muestran el IGV configurado en *Empresa y marca* (los
precios ya lo incluyen; el ticket solo muestra el desglose "Op. gravada + IGV"). La nota
de venta no desglosa IGV porque no es un comprobante tributario.

El sistema **no deja** anular ni eliminar cuando:

- La venta ya tiene cobros o la compra ya tiene pagos registrados.
- El documento pertenece a una **caja ya cerrada** (rompería el arqueo).
- La mercadería de esa compra ya se vendió (o ya se envió a otro almacén).
- La venta ya tiene devoluciones parciales: se devuelve el resto en lugar de anular.

---

## Preguntas frecuentes

**Cambié el tipo de negocio y no veo las funciones nuevas.**
Recarga la página con `Ctrl+F5`. Si sigues sin verlas, revisa que guardaste la
configuración.

**No puedo vender un producto que sí tiene stock.**
Tres motivos posibles: el stock está vencido (revisa *Vencimientos*), la talla que
elegiste está agotada aunque el modelo tenga stock en otras, o el artículo está
desactivado.

**Quiero quitar una talla y no me deja.**
Tiene stock. Retíralo primero con un ajuste de salida y después quítala. Al quitarla
se conserva su historial de ventas.

**Vendí una caja pero quiero saber cuántas unidades salieron.**
El kardex (*Inventario → Kardex y alertas*) muestra siempre unidades base: una caja
de 12 aparece como 12.

**¿Cómo respaldo mis datos?**
*Configuración → Backup y restauración*: genera la copia y **descárgala**. Guárdala fuera del
servidor (en tu computadora o en la nube). Hazlo al menos una vez por semana.

**Se me olvidó cerrar la caja de ayer.**
Ábrela desde *Finanzas → Caja diaria* y ciérrala con el monto real contado. El
sistema calcula la diferencia y la deja registrada.
