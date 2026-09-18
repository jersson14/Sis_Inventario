<?php
/**
 * Nota de credito en PDF A4.   ?id=N (idnota)
 */
ob_start();
require_once "../config/seguridad.php";
requiereLogin(false);
if (!usuarioTienePermiso('ventas')) {
  echo "No tiene permiso para visualizar la nota de crédito";
  ob_end_flush();
  exit;
}
require_once "../fpdf181/fpdf.php";
require_once "../modelos/NotaCredito.php";
require_once "../modelos/Empresa.php";
require_once "Letras.php";

$idnota = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
$ncModel = new NotaCredito();
$cab = $ncModel->cabecera($idnota);
if (!$cab) {
  echo "No se encontró la nota de crédito.";
  ob_end_flush();
  exit;
}
if (!puedeVerTodasLasVentas() && (int)$cab['idvendedor'] !== (int)$_SESSION['idusuario'] && (int)$cab['idusuario'] !== (int)$_SESSION['idusuario']) {
  echo "Solo puedes ver las notas de tus ventas";
  ob_end_flush();
  exit;
}
$items = $ncModel->detalle($idnota);
$emp = (new Empresa())->datosReporte();
$codMoneda = !empty($emp['moneda']) ? $emp['moneda'] : 'PEN';
$sim = obtenerSimboloMoneda($codMoneda);
$u = function ($t) { return mb_convert_encoding((string)$t, 'ISO-8859-1', 'UTF-8'); };
$numero = $cab['serie'] . '-' . $cab['numero'];
$marca = marcaEmpresa();
$rgb = sscanf($marca['primario'], '#%02x%02x%02x');

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// Cabecera: empresa a la izquierda, recuadro del documento a la derecha
$logo = marcaRutaLogoPdf();
$x = 12;
if ($logo !== '' && is_file($logo)) {
  $pdf->Image($logo, 12, 12, 34, 18);
  $x = 50;
}
$pdf->SetXY($x, 12);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(90, 6, $u($emp['nombre_comercial'] !== '' ? $emp['nombre_comercial'] : $emp['nombre']), 0, 2);
$pdf->SetFont('Arial', '', 9);
if ($emp['ruc'] !== '') { $pdf->Cell(90, 4.5, $u('RUC: ' . $emp['ruc']), 0, 2); }
if ($emp['direccion_linea1'] !== '') { $pdf->Cell(90, 4.5, $u($emp['direccion_linea1']), 0, 2); }
if ($emp['telefono'] !== '') { $pdf->Cell(90, 4.5, $u('Tel: ' . $emp['telefono']), 0, 2); }

$pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
$pdf->SetLineWidth(0.6);
$pdf->Rect(140, 12, 58, 24);
$pdf->SetXY(140, 15);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(58, 6, $u('NOTA DE CRÉDITO'), 0, 2, 'C');
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(58, 7, $u($numero), 0, 2, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(58, 5, $u($cab['fecha']), 0, 2, 'C');
$pdf->SetLineWidth(0.2);

// Datos
$pdf->SetXY(12, 42);
$pdf->SetFillColor(248, 250, 252);
$pdf->SetDrawColor(214, 223, 233);
$pdf->Rect(12, 42, 186, 30, 'DF');
$pdf->SetXY(15, 44);
$linea = function ($etq, $val) use ($pdf, $u) {
  $pdf->SetX(15);
  $pdf->SetFont('Arial', 'B', 9);
  $pdf->Cell(38, 5, $u($etq), 0, 0);
  $pdf->SetFont('Arial', '', 9);
  $pdf->Cell(140, 5, $u($val), 0, 1);
};
$linea('Cliente:', $cab['cliente'] . (trim((string)$cab['num_documento']) !== '' ? '  (' . $cab['tipo_documento'] . ' ' . $cab['num_documento'] . ')' : ''));
$linea('Documento que modifica:', $cab['tipo_comprobante'] . ' ' . $cab['serie_comprobante'] . '-' . $cab['num_comprobante'] . ' del ' . $cab['fecha_venta']);
$linea('Tipo de nota:', $cab['tipo_nota'] . ' - ' . (isset(NotaCredito::TIPOS[$cab['tipo_nota']]) ? NotaCredito::TIPOS[$cab['tipo_nota']] : ''));
$linea('Motivo:', $cab['motivo']);
$linea('Registró / autorizó:', $cab['usuario'] . ($cab['autorizo'] !== '' ? ' / ' . $cab['autorizo'] : ''));

// Detalle
$pdf->SetXY(12, 77);
$pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$anchos = array(26, 78, 22, 20, 18, 22);
$cabs = array('CÓDIGO', 'DESCRIPCIÓN', 'CANTIDAD', 'P.UNIT', 'DSCTO', 'IMPORTE');
foreach ($cabs as $i => $c) { $pdf->Cell($anchos[$i], 7, $u($c), 0, 0, $i < 2 ? 'L' : 'R', true); }
$pdf->Ln();
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Arial', '', 9);
foreach ($items as $k => $it) {
  $pdf->SetFillColor($k % 2 ? 255 : 248, $k % 2 ? 255 : 250, $k % 2 ? 255 : 252);
  $nombre = html_entity_decode($it['articulo'], ENT_QUOTES, 'UTF-8') . ((int)$it['reingresa_stock'] === 1 ? '' : ' (dañado)');
  $pdf->Cell($anchos[0], 6.5, $u($it['codigo']), 0, 0, 'L', true);
  $pdf->Cell($anchos[1], 6.5, $u(mb_substr($nombre, 0, 48, 'UTF-8')), 0, 0, 'L', true);
  $pdf->Cell($anchos[2], 6.5, $u(formatearCantidad($it['cantidad']) . ' ' . $it['unidad']), 0, 0, 'R', true);
  $pdf->Cell($anchos[3], 6.5, number_format((float)$it['precio'], 2), 0, 0, 'R', true);
  $pdf->Cell($anchos[4], 6.5, number_format((float)$it['descuento'], 2), 0, 0, 'R', true);
  $pdf->Cell($anchos[5], 6.5, number_format((float)$it['subtotal'], 2), 0, 1, 'R', true);
}

// Totales y reintegro
$pdf->Ln(4);
$y = $pdf->GetY();
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetXY(130, $y);
$pdf->Cell(40, 7, 'TOTAL', 0, 0, 'L');
$pdf->Cell(28, 7, $u($sim . ' ' . number_format((float)$cab['total'], 2)), 0, 1, 'R');
$pdf->SetFont('Arial', '', 9);
$pdf->SetXY(12, $y);
$letras = (new EnLetras())->ValorEnLetras(round((float)$cab['total'], 2), obtenerNombreMonedaLetras($codMoneda));
$pdf->MultiCell(112, 5, $u('SON: ' . $letras), 0, 'L');
$pdf->Ln(2);
$medios = array('EFECTIVO' => 'efectivo', 'YAPE' => 'Yape', 'PLIN' => 'Plin', 'TARJETA' => 'extorno a tarjeta', 'TRANSFERENCIA' => 'transferencia',
  'DEPOSITO' => 'depósito', 'OTRO' => 'otro medio', 'SALDO_A_FAVOR' => 'saldo a favor del cliente', 'ORIGINAL' => 'los mismos medios de la venta');
$partes = array();
if ((float)$cab['monto_credito'] > 0) { $partes[] = ($cab['reintegro'] === 'ORIGINAL' ? 'Deuda anulada: ' : 'Descontado de la deuda: ') . $sim . ' ' . number_format((float)$cab['monto_credito'], 2); }
if ((float)$cab['monto_reintegro'] > 0) { $partes[] = 'Devuelto por ' . (isset($medios[$cab['reintegro']]) ? $medios[$cab['reintegro']] : $cab['reintegro']) . ': ' . $sim . ' ' . number_format((float)$cab['monto_reintegro'], 2); }
if ((float)$cab['saldo_favor'] > 0) { $partes[] = 'Saldo a favor disponible: ' . $sim . ' ' . number_format((float)$cab['saldo_favor'], 2); }
$pdf->SetFont('Arial', 'B', 9);
$pdf->MultiCell(186, 5, $u(implode('   ·   ', $partes)), 0, 'L');

$pdf->Ln(22);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(80, 5, '______________________________', 0, 0, 'C');
$pdf->Cell(26, 5, '', 0, 0);
$pdf->Cell(80, 5, '______________________________', 0, 1, 'C');
$pdf->Cell(80, 5, $u('Firma del cliente'), 0, 0, 'C');
$pdf->Cell(26, 5, '', 0, 0);
$pdf->Cell(80, 5, $u('Firma de la tienda'), 0, 1, 'C');

$pdf->Output('Nota_credito_' . $numero . '.pdf', 'I');
ob_end_flush();
