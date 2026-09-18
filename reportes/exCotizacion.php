<?php
ob_start();
require_once "../config/seguridad.php";
requiereLogin(false);
if (!usuarioTienePermiso('ventas')) {
  echo "No tiene permiso para visualizar el reporte";
  ob_end_flush();
  exit;
}
$idReporte = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);

require_once "../fpdf181/fpdf.php";
require_once "../modelos/Cotizacion.php";
require_once "../modelos/Empresa.php";
require_once "Letras.php";

class PDFCotizacion extends FPDF
{
  public $empresa = array();
  public $documento = array();
  public $cliente = array();
  public $logo = "";
  protected $widths = array();
  protected $aligns = array();

  public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
  {
    parent::__construct($orientation, $unit, $size);
    $this->SetMargins(10, 84, 10);
    $this->SetAutoPageBreak(true, 24);
  }

  public function u($text) { return textoLatin1(html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8')); }

  public function fitText($text, $maxWidth, $suffix = '...')
  {
    $txt = (string)$text;
    if ($txt === '' || $this->GetStringWidth($txt) <= $maxWidth) return $txt;
    $sw = $this->GetStringWidth($suffix);
    while (strlen($txt) > 0 && $this->GetStringWidth($txt) + $sw > $maxWidth) { $txt = substr($txt, 0, -1); }
    return rtrim($txt) . $suffix;
  }

  public function Header()
  {
    $this->SetDrawColor(214, 223, 233);
    $this->SetFillColor(11, 79, 74);
    $this->Rect(10, 10, 190, 36, 'F');
    $this->SetFillColor(255, 255, 255);
    $this->Rect(12, 12, 34, 20, 'F');
    if (!empty($this->logo) && file_exists($this->logo)) { $this->Image($this->logo, 13, 13, 32, 18); }

    $leftX = 48; $leftW = 80;
    $this->SetTextColor(255, 255, 255);
    $nombre = $this->u($this->empresa["nombre"]);
    $f = 13; $this->SetFont('Arial', 'B', $f);
    while ($this->GetStringWidth($nombre) > $leftW && $f > 10) { $f -= 0.5; $this->SetFont('Arial', 'B', $f); }
    $this->SetXY($leftX, 13); $this->Cell($leftW, 5.6, $this->fitText($nombre, $leftW), 0, 1, 'L');
    $this->SetFont('Arial', '', 10); $this->SetXY($leftX, 18.5); $this->Cell($leftW, 4.2, $this->fitText($this->u("RUC: " . $this->empresa["ruc"]), $leftW), 0, 1, 'L');
    $this->SetFont('Arial', '', 9.4); $this->SetXY($leftX, 22.7); $this->Cell($leftW, 3.8, $this->fitText($this->u($this->empresa["direccion_linea1"]), $leftW), 0, 1, 'L');
    $this->SetXY($leftX, 26.5); $this->Cell($leftW, 3.8, $this->fitText($this->u($this->empresa["direccion_linea2"]), $leftW), 0, 1, 'L');
    $this->SetFont('Arial', '', 9.3); $this->SetXY($leftX, 30.4); $this->Cell($leftW, 3.8, $this->fitText($this->u("Tel: " . $this->empresa["telefono"]), $leftW), 0, 1, 'L');
    $this->SetXY($leftX, 34.2); $this->Cell($leftW, 3.8, $this->fitText($this->u("Email: " . $this->empresa["email"]), $leftW), 0, 1, 'L');

    $this->SetFillColor(245, 158, 11); $this->SetDrawColor(161, 98, 7);
    $this->Rect(132, 12, 66, 12, 'DF');
    $this->SetTextColor(17, 24, 39); $this->SetFont('Arial', 'B', 12);
    $this->SetXY(132, 15.2); $this->Cell(66, 5, $this->u($this->documento["titulo"]), 0, 1, 'C');

    $this->SetFillColor(241, 245, 249); $this->SetDrawColor(203, 213, 225);
    $this->Rect(132, 25, 66, 19, 'DF');
    $this->SetTextColor(15, 23, 42); $this->SetFont('Arial', 'B', 9);
    $this->SetXY(132, 26.2); $this->Cell(33, 4.4, 'FECHA', 0, 0, 'C'); $this->Cell(33, 4.4, $this->u('VÁLIDA HASTA'), 0, 1, 'C');
    $this->SetFont('Arial', '', 9.5);
    $this->SetXY(132, 31); $this->Cell(33, 5, $this->documento["fecha"], 0, 0, 'C'); $this->Cell(33, 5, $this->documento["validez"], 0, 1, 'C');
    $this->SetFont('Arial', 'B', 9); $this->SetXY(132, 37.5); $this->Cell(66, 5, $this->u('Estado: ' . $this->documento["estado"]), 0, 1, 'C');

    $this->SetDrawColor(214, 223, 233); $this->SetFillColor(248, 250, 252);
    $this->Rect(10, 48, 190, 26, 'DF');
    $this->SetTextColor(30, 41, 59); $this->SetFont('Arial', 'B', 10);
    $this->SetXY(12, 50); $this->Cell(40, 5, $this->u('CLIENTE'), 0, 1, 'L');
    $this->SetFont('Arial', '', 9.6);
    $this->SetXY(12, 54.5); $this->Cell(125, 4.6, $this->u("Nombre: " . $this->cliente["nombre"]), 0, 1, 'L');
    $this->SetX(12); $this->Cell(125, 4.6, $this->u("Documento: " . $this->cliente["documento"]), 0, 1, 'L');
    $this->SetX(12); $this->Cell(125, 4.6, $this->u("Direccion: " . $this->cliente["direccion"]), 0, 1, 'L');
    $this->SetX(12); $this->Cell(125, 4.6, $this->u("Email: " . $this->cliente["email"] . "  |  Telefono: " . $this->cliente["telefono"]), 0, 1, 'L');
  }

  public function Footer()
  {
    $this->SetY(-12); $this->SetFont('Arial', 'I', 8); $this->SetTextColor(100, 116, 139);
    $this->Cell(0, 5, $this->u('Cotización sin valor tributario. Precios sujetos a cambio tras la fecha de validez.'), 0, 0, 'L');
    $this->Cell(0, 5, $this->u('Página ') . $this->PageNo(), 0, 0, 'R');
  }

  public function SetWidths($w) { $this->widths = $w; }
  public function SetAligns($a) { $this->aligns = $a; }

  public function DrawTableHeader()
  {
    $headers = array('CODIGO', 'DESCRIPCION', 'CANTIDAD', 'P.U.', 'DSCTO', 'SUBTOTAL');
    $this->SetFont('Arial', 'B', 9.6); $this->SetTextColor(255, 255, 255); $this->SetFillColor(15, 118, 110);
    for ($i = 0; $i < count($headers); $i++) { $this->Cell($this->widths[$i], 8, $this->u($headers[$i]), 1, 0, 'C', true); }
    $this->Ln(); $this->SetTextColor(17, 24, 39); $this->SetFont('Arial', '', 9.4);
  }

  public function Row($data, $fill = false)
  {
    $h = 6;
    if ($this->GetY() + $h > $this->PageBreakTrigger) { $this->AddPage($this->CurOrientation); $this->DrawTableHeader(); }
    for ($i = 0; $i < count($data); $i++) {
      $w = $this->widths[$i]; $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
      if ($fill) { $this->SetFillColor(248, 250, 252); }
      $this->Cell($w, $h, $this->fitText($data[$i], $w - 2), 1, 0, $a, $fill);
    }
    $this->Ln($h);
  }
}

$cot = new Cotizacion();
$c = $cot->mostrar($idReporte);
if (!$c) { echo "No se encontró la cotización solicitada."; ob_end_flush(); exit; }

$empresaModel = new Empresa();
$empresa = $empresaModel->datosReporte();
$codigoMoneda = !empty($empresa["moneda"]) ? strtoupper((string)$empresa["moneda"]) : 'PEN';
$simbolo = obtenerSimboloMoneda($codigoMoneda);
$monedaLetras = obtenerNombreMonedaLetras($codigoMoneda);

// Logo de la empresa (JPG/PNG; un WEBP se convierte). Sin logo, el PDF sale sin imagen.
$logo = marcaRutaLogoPdf();

$pdf = new PDFCotizacion('P', 'mm', 'A4');
$pdf->empresa = $empresa;
$pdf->documento = array("titulo" => "COTIZACIÓN " . $c['numero'], "fecha" => date('d/m/Y', strtotime($c['fecha_hora'])), "validez" => date('d/m/Y', strtotime($c['fecha_validez'])), "estado" => $c['estado']);
$pdf->cliente = array("nombre" => $c['cliente'], "documento" => $c['tipo_documento'] . ": " . $c['num_documento'], "direccion" => $c['direccion'] !== '' ? $c['direccion'] : '-', "email" => $c['email'] !== '' ? $c['email'] : '-', "telefono" => $c['telefono'] !== '' ? $c['telefono'] : '-');
$pdf->logo = $logo;
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 10); $pdf->SetTextColor(15, 23, 42);
$pdf->Cell(190, 7, $pdf->u('DETALLE DE LA COTIZACIÓN'), 0, 1, 'L');
$pdf->SetWidths(array(26, 74, 28, 20, 18, 24));
$pdf->SetAligns(array('L', 'L', 'C', 'R', 'R', 'R'));
$pdf->DrawTableHeader();

$rs = $cot->detalles($idReporte);
$i = 0;
if ($rs) {
  while ($d = $rs->fetch_object()) {
    $pdf->Row(array($pdf->u($d->codigo), $pdf->u($d->nombre), formatearCantidad($d->cantidad) . " " . $pdf->u($d->unidad), number_format((float)$d->precio, 2), number_format((float)$d->descuento, 2), number_format((float)$d->subtotal, 2)), ($i % 2) === 0);
    $i++;
  }
}

$impuesto = (float)$c['impuesto'];
$total = (float)$c['total'];
$factor = 1 + ($impuesto / 100); if ($factor <= 0) $factor = 1;
$subtotal = $total / $factor; $igv = $total - $subtotal;

$V = new EnLetras(); $V->substituir_un_mil_por_mil = true;
$letras = strtoupper(trim($V->ValorEnLetras(round($total, 2), " " . $monedaLetras)));
$letras = preg_replace('/\s+/', ' ', str_replace('--', '', $letras));

if ($pdf->GetY() > 200) { $pdf->AddPage(); }
$y = $pdf->GetY() + 6;
$pdf->SetDrawColor(214, 223, 233); $pdf->SetFillColor(248, 250, 252);
$pdf->Rect(10, $y, 122, 24, 'DF');
$pdf->SetFont('Arial', 'B', 9.5); $pdf->SetTextColor(30, 41, 59);
$pdf->SetXY(13, $y + 2.5); $pdf->Cell(116, 5, $pdf->u('TOTAL EN LETRAS'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 9.2); $pdf->SetXY(13, $y + 8); $pdf->MultiCell(116, 5, $pdf->u($letras), 0, 'L');

$bx = 137; $bw = 63;
$pdf->SetFillColor(248, 250, 252); $pdf->Rect($bx, $y, $bw, 24, 'DF');
$pdf->SetFillColor(15, 118, 110); $pdf->SetTextColor(255, 255, 255); $pdf->Rect($bx, $y, $bw, 6, 'F');
$pdf->SetFont('Arial', 'B', 9.5); $pdf->SetXY($bx, $y + 1.2); $pdf->Cell($bw, 4, $pdf->u('RESUMEN'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9.2); $pdf->SetTextColor(30, 41, 59);
$pdf->SetXY($bx + 2, $y + 8); $pdf->Cell(30, 4.4, 'SUBTOTAL', 0, 0, 'L'); $pdf->Cell(29, 4.4, $simbolo . ' ' . number_format($subtotal, 2), 0, 1, 'R');
$pdf->SetX($bx + 2); $pdf->Cell(30, 4.4, 'IMPUESTO (' . number_format($impuesto, 2) . '%)', 0, 0, 'L'); $pdf->Cell(29, 4.4, $simbolo . ' ' . number_format($igv, 2), 0, 1, 'R');
$pdf->SetX($bx + 2); $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(30, 4.8, 'TOTAL', 0, 0, 'L'); $pdf->Cell(29, 4.8, $simbolo . ' ' . number_format($total, 2), 0, 1, 'R');

$pdf->SetY($y + 30);
if (!empty($c['observacion'])) { $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(30, 5, $pdf->u('Observación:'), 0, 0); $pdf->SetFont('Arial', '', 9); $pdf->MultiCell(160, 5, $pdf->u($c['observacion']), 0, 'L'); }
if (!empty($c['condiciones'])) { $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(30, 5, $pdf->u('Condiciones:'), 0, 0); $pdf->SetFont('Arial', '', 9); $pdf->MultiCell(160, 5, $pdf->u($c['condiciones']), 0, 'L'); }
$pdf->Ln(4); $pdf->SetFont('Arial', 'I', 8.5); $pdf->SetTextColor(100, 116, 139);
$pdf->Cell(190, 5, $pdf->u('Atendido por: ' . $c['usuario']), 0, 1, 'L');

$pdf->Output('Cotizacion_' . $c['numero'] . '.pdf', 'I');
ob_end_flush();
