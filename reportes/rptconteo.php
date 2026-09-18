<?php
/**
 * Reporte imprimible de una toma de inventario (A4): lo contado, el stock del
 * sistema, las diferencias en unidades y soles, los ajustes aplicados y firmas.
 *   ?id=N
 */
ob_start();
require_once "../config/seguridad.php";
requiereLogin(false);
if (!usuarioTienePermiso('inventario') && !usuarioTienePermiso('almacen')) {
  echo "No tiene permiso para ver el reporte";
  ob_end_flush();
  exit;
}
require_once "../modelos/Conteo.php";
require_once "../modelos/Empresa.php";

$id = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
$conteoModel = new Conteo();
$c = $conteoModel->obtener($id);
if (!$c) {
  echo "No se encontró el conteo.";
  ob_end_flush();
  exit;
}
$lineas = $conteoModel->lineas($id);
usort($lineas, function ($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });
// Ajustes hechos a lo que no se conto (opcion "poner en cero")
$noContados = dbAll(
  "SELECT aj.cantidad, aj.costo_unitario, a.nombre, IFNULL(um.abreviatura,'und') AS unidad,
     IFNULL(NULLIF(CONCAT_WS(' / ', NULLIF(av.talla,''), NULLIF(av.color,'')),''),'') AS variante
   FROM ajuste_inventario aj
   INNER JOIN articulo a ON a.idarticulo=aj.idarticulo
   LEFT JOIN unidad_medida um ON um.idunidad=a.idunidad
   LEFT JOIN articulo_variante av ON av.idvariante=aj.idvariante
   WHERE aj.idconteo=? AND aj.observacion LIKE '%(no contado)'
   ORDER BY a.nombre",
  array($id)
);
$emp = (new Empresa())->datosReporte();
$marca = marcaEmpresa();

$sobrante = 0.0;
$faltante = 0.0;
$conDif = 0;
foreach ($lineas as $l) {
  if (abs($l['diferencia']) >= 0.0005) {
    $conDif++;
    if ($l['valor'] > 0) { $sobrante += $l['valor']; } else { $faltante += -$l['valor']; }
  }
}
$valorNoContados = 0.0;
foreach ($noContados as $n) {
  $valorNoContados += (float)$n['cantidad'] * (float)$n['costo_unitario'];
}
$aplicado = $c['estado'] === 'APLICADO';
$porLote = (int)$c['por_lote'] === 1;

function rMoneda($v) { return formatearMoneda($v); }
function rDif($v, $texto = null) {
  $v = round((float)$v, 3);
  if (abs($v) < 0.0005) { return '<span class="muted">0</span>'; }
  return '<span class="' . ($v > 0 ? 'mas' : 'menos') . '">' . ($v > 0 ? '+' : '−') . e($texto !== null ? $texto : formatearCantidad(abs($v))) . '</span>';
}
function rDetalle($l) {
  if ($l['variante'] !== '') { return e($l['variante']); }
  if ($l['tipo_lote'] === 'lote' || $l['tipo_lote'] === 'nuevo') {
    return 'Lote ' . e($l['lote_codigo'] ?: 's/c') . ($l['lote_vencimiento'] ? ' · ' . date('d/m/Y', strtotime($l['lote_vencimiento'])) : '') . ($l['tipo_lote'] === 'nuevo' ? ' (nuevo)' : '');
  }
  return $l['tipo_lote'] === 'sin_lote' ? 'Sin lote' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Conteo #<?php echo (int)$id; ?> · <?php echo e($c['nombre']); ?></title>
  <style>
    @page { size: A4; margin: 12mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e5e7eb; color: #0f172a; font: 12px/1.4 Arial, Helvetica, sans-serif; }
    .hoja { max-width: 210mm; margin: 12px auto; background: #fff; padding: 12mm; box-shadow: 0 6px 20px rgba(0,0,0,.15); }
    .barra { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
    .barra button { border: 0; border-radius: 6px; padding: 8px 16px; font-weight: 700; cursor: pointer; background: #0f766e; color: #fff; }
    .barra button + button { background: #334155; }
    header { display: flex; gap: 14px; align-items: center; border-bottom: 3px solid <?php echo e($marca['primario']); ?>; padding-bottom: 10px; }
    header img { max-height: 54px; max-width: 150px; }
    header h1 { margin: 0; font-size: 18px; }
    header .sub { color: #475569; }
    h2 { font-size: 16px; margin: 14px 0 6px; }
    .datos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px 16px; margin: 10px 0; }
    .datos b { color: #475569; font-weight: 600; }
    .tarjetas { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 10px 0 4px; }
    .tarjeta { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; }
    .tarjeta span { display: block; color: #475569; font-size: 11px; }
    .tarjeta strong { font-size: 15px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; vertical-align: top; }
    th { background: #f1f5f9; text-align: left; font-size: 11px; text-transform: uppercase; }
    td.n, th.n { text-align: right; white-space: nowrap; }
    tr.dif td { background: #fffbeb; }
    .mas { color: #15803d; font-weight: 700; }
    .menos { color: #b91c1c; font-weight: 700; }
    .muted { color: #94a3b8; }
    .estado { display: inline-block; padding: 2px 8px; border-radius: 10px; font-weight: 700; font-size: 11px; background: #e2e8f0; }
    .estado.APLICADO { background: #dcfce7; color: #166534; }
    .estado.ABIERTO { background: #fef3c7; color: #92400e; }
    .firmas { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 46px; text-align: center; }
    .firmas div { border-top: 1px solid #0f172a; padding-top: 4px; }
    .pie { margin-top: 16px; color: #64748b; font-size: 10px; text-align: right; }
    @media print {
      body { background: #fff; }
      .barra { display: none; }
      .hoja { margin: 0; padding: 0; box-shadow: none; max-width: none; }
      tr { break-inside: avoid; }
    }
  </style>
</head>
<body>
<div class="barra"><button type="button" onclick="window.print()">Imprimir</button><button type="button" onclick="window.close()">Cerrar</button></div>
<div class="hoja">
  <header>
<?php if ($marca['tiene_logo']) { ?>
    <img src="<?php echo e(marcaUrlLogo('../')); ?>" alt="">
<?php } ?>
    <div>
      <h1><?php echo e($emp['nombre_comercial'] !== '' ? $emp['nombre_comercial'] : $emp['nombre']); ?></h1>
      <div class="sub"><?php echo $emp['ruc'] !== '' ? 'RUC ' . e($emp['ruc']) . ' · ' : ''; ?>Reporte de toma de inventario</div>
    </div>
  </header>

  <h2>Conteo #<?php echo (int)$id; ?>: <?php echo e(html_entity_decode($c['nombre'], ENT_QUOTES, 'UTF-8')); ?> <span class="estado <?php echo e($c['estado']); ?>"><?php echo e($c['estado']); ?></span></h2>
  <div class="datos">
    <div><b>Alcance:</b> <?php echo $c['categoria'] !== '' ? e(html_entity_decode($c['categoria'], ENT_QUOTES, 'UTF-8')) : 'Todo el almacén'; ?><?php echo $porLote ? ' (por lote)' : ''; ?><?php echo !empty($c['almacen']) ? ' · ' . e(html_entity_decode($c['almacen'], ENT_QUOTES, 'UTF-8')) : ''; ?></div>
    <div><b>Inicio:</b> <?php echo e(date('d/m/Y H:i', strtotime($c['fecha_inicio']))); ?> · <?php echo e($c['usuario']); ?></div>
    <div><b>Cierre:</b> <?php echo $c['fecha_cierre'] ? e(date('d/m/Y H:i', strtotime($c['fecha_cierre']))) . ' · ' . e($c['usuario_cierre']) : '—'; ?></div>
<?php if (!empty($c['observacion'])) { ?>
    <div style="grid-column:1/-1"><b>Observación:</b> <?php echo e(html_entity_decode($c['observacion'], ENT_QUOTES, 'UTF-8')); ?></div>
<?php } ?>
  </div>

  <div class="tarjetas">
    <div class="tarjeta"><span>Líneas contadas</span><strong><?php echo count($lineas); ?></strong></div>
    <div class="tarjeta"><span>Con diferencia</span><strong><?php echo $conDif; ?></strong></div>
    <div class="tarjeta"><span>Sobrante (a costo)</span><strong class="mas">+<?php echo e(rMoneda($sobrante)); ?></strong></div>
    <div class="tarjeta"><span>Faltante (a costo)</span><strong class="menos">−<?php echo e(rMoneda($faltante + $valorNoContados)); ?></strong></div>
  </div>
<?php if (!$aplicado) { ?>
  <p class="muted"><?php echo $c['estado'] === 'ABIERTO' ? 'El conteo sigue abierto: las diferencias son provisionales y aún no se ajustó el stock.' : 'Conteo anulado: el stock no se modificó.'; ?></p>
<?php } ?>

  <h2>Detalle de lo contado</h2>
  <table>
    <thead><tr><th>Artículo</th><th>Código</th><th>Talla / lote</th><th class="n">Contado</th><th class="n">Sistema</th><th class="n">Diferencia</th><th class="n">Costo</th><th class="n">Valor</th></tr></thead>
    <tbody>
<?php foreach ($lineas as $l) { ?>
      <tr<?php echo abs($l['diferencia']) >= 0.0005 ? ' class="dif"' : ''; ?>>
        <td><?php echo e($l['nombre']); ?></td>
        <td><?php echo e($l['codigo']); ?></td>
        <td><?php echo rDetalle($l); ?></td>
        <td class="n"><?php echo formatearCantidad($l['cantidad']) . ' ' . e($l['unidad']); ?></td>
        <td class="n"><?php echo formatearCantidad($l['stock_sistema']); ?></td>
        <td class="n"><?php echo rDif($l['diferencia']); ?></td>
        <td class="n"><?php echo number_format((float)$l['costo_unitario'], 2); ?></td>
        <td class="n"><?php echo abs($l['valor']) >= 0.005 ? rDif($l['valor'], rMoneda(abs($l['valor']))) : '<span class="muted">—</span>'; ?></td>
      </tr>
<?php } ?>
<?php if (!$lineas) { ?>
      <tr><td colspan="8" class="muted">Sin lecturas.</td></tr>
<?php } ?>
    </tbody>
  </table>

<?php if ($noContados) { ?>
  <h2>Puesto en cero por no contarse</h2>
  <table>
    <thead><tr><th>Artículo</th><th>Talla</th><th class="n">Cantidad retirada</th><th class="n">Valor</th></tr></thead>
    <tbody>
<?php   foreach ($noContados as $n) { ?>
      <tr><td><?php echo e(html_entity_decode($n['nombre'], ENT_QUOTES, 'UTF-8')); ?></td><td><?php echo e($n['variante']); ?></td>
        <td class="n"><?php echo formatearCantidad($n['cantidad']) . ' ' . e($n['unidad']); ?></td>
        <td class="n"><span class="menos">−<?php echo e(rMoneda((float)$n['cantidad'] * (float)$n['costo_unitario'])); ?></span></td></tr>
<?php   } ?>
    </tbody>
  </table>
<?php } ?>

  <div class="firmas">
    <div>Contó</div>
    <div>Revisó</div>
    <div>Aprobó</div>
  </div>
  <div class="pie">Generado el <?php echo date('d/m/Y H:i'); ?> por <?php echo e($_SESSION['nombre']); ?></div>
</div>
</body>
</html>
<?php
ob_end_flush();
