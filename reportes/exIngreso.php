<?php
ob_start();
if (strlen(session_id()) < 1) {
  session_start();
}

if (!isset($_SESSION['nombre'])) {
  echo "Debe ingresar al sistema correctamente para visualizar el reporte";
  ob_end_flush();
  exit;
}

if ($_SESSION['compras'] != 1) {
  echo "No tiene permiso para visualizar el reporte";
  ob_end_flush();
  exit;
}

require_once "../fpdf181/fpdf.php";
require_once "../modelos/Ingreso.php";
require_once "../modelos/Empresa.php";
require_once "Letras.php";

class PDFCompra extends FPDF
{
  public $empresa = array();
  public $documento = array();
  public $proveedor = array();
  public $logo = "";

  protected $widths = array();
  protected $aligns = array();

  public function __construct($orientation='P', $unit='mm', $size='A4')
  {
    parent::__construct($orientation, $unit, $size);
    $this->SetMargins(10, 84, 10);
    $this->SetAutoPageBreak(true, 24);
  }

  public function u($text)
  {
    return utf8_decode((string)$text);
  }

  public function fitText($text, $maxWidth, $suffix = '...')
  {
    $txt = (string)$text;
    if ($txt === '') {
      return '';
    }
    if ($this->GetStringWidth($txt) <= $maxWidth) {
      return $txt;
    }

    $suffixWidth = $this->GetStringWidth($suffix);
    while (strlen($txt) > 0 && $this->GetStringWidth($txt) + $suffixWidth > $maxWidth) {
      $txt = substr($txt, 0, -1);
    }
    return rtrim($txt) . $suffix;
  }

  public function Header()
  {
    $this->SetDrawColor(214, 223, 233);
    $this->SetFillColor(2, 132, 199);
    $this->Rect(10, 10, 190, 36, 'F');

    $this->SetFillColor(255, 255, 255);
    $this->Rect(12, 12, 22, 22, 'F');
    if (!empty($this->logo) && file_exists($this->logo)) {
      $this->Image($this->logo, 13, 13, 20, 20);
    }

    $leftX = 36;
    $leftW = 90;
    $this->SetTextColor(255, 255, 255);

    $nombreEmpresa = $this->u($this->empresa["nombre"]);
    $fontEmpresa = 13;
    $this->SetFont('Arial', 'B', $fontEmpresa);
    while ($this->GetStringWidth($nombreEmpresa) > $leftW && $fontEmpresa > 10) {
      $fontEmpresa -= 0.5;
      $this->SetFont('Arial', 'B', $fontEmpresa);
    }
    $this->SetXY($leftX, 13);
    $this->Cell($leftW, 5.6, $this->fitText($nombreEmpresa, $leftW), 0, 1, 'L');

    $this->SetFont('Arial', '', 10);
    $this->SetXY($leftX, 18.5);
    $this->Cell($leftW, 4.2, $this->fitText($this->u("RUC: ".$this->empresa["ruc"]), $leftW), 0, 1, 'L');

    $this->SetFont('Arial', '', 9.4);
    $this->SetXY($leftX, 22.7);
    $this->Cell($leftW, 3.8, $this->fitText($this->u($this->empresa["direccion_linea1"]), $leftW), 0, 1, 'L');
    $this->SetXY($leftX, 26.5);
    $this->Cell($leftW, 3.8, $this->fitText($this->u($this->empresa["direccion_linea2"]), $leftW), 0, 1, 'L');

    $this->SetFont('Arial', '', 9.3);
    $this->SetXY($leftX, 30.4);
    $this->Cell($leftW, 3.8, $this->fitText($this->u("Tel: ".$this->empresa["telefono"]), $leftW), 0, 1, 'L');
    $this->SetXY($leftX, 34.2);
    $this->Cell($leftW, 3.8, $this->fitText($this->u("Email: ".$this->empresa["email"]), $leftW), 0, 1, 'L');

    $this->SetFillColor(245, 158, 11);
    $this->SetDrawColor(161, 98, 7);
    $this->Rect(132, 12, 66, 12, 'DF');
    $tituloDoc = $this->u($this->documento["titulo"]);
    $fontTitulo = 12;
    $this->SetTextColor(17, 24, 39);
    $this->SetFont('Arial', 'B', $fontTitulo);
    while ($this->GetStringWidth($tituloDoc) > 62 && $fontTitulo > 9) {
      $fontTitulo -= 0.5;
      $this->SetFont('Arial', 'B', $fontTitulo);
    }
    $this->SetXY(132, 15.2);
    $this->Cell(66, 5, $tituloDoc, 0, 1, 'C');

    // Bloque de fecha con fondo claro para asegurar contraste de impresión
    $this->SetFillColor(241, 245, 249);
    $this->SetDrawColor(203, 213, 225);
    $this->Rect(132, 25, 66, 19, 'DF');
    $this->SetDrawColor(214, 223, 233);
    $this->Line(132, 32.8, 198, 32.8);
    $this->SetTextColor(15, 23, 42);
    $this->SetFont('Arial', 'B', 10);
    $this->SetXY(132, 27.0);
    $this->Cell(66, 4.4, 'FECHA', 0, 1, 'C');
    $this->SetFont('Arial', '', 10);
    $this->SetXY(132, 34.0);
    $this->Cell(66, 5, $this->documento["fecha"], 0, 1, 'C');

    $this->SetDrawColor(214, 223, 233);
    $this->SetFillColor(248, 250, 252);
    $this->Rect(10, 48, 190, 26, 'DF');
    $this->SetTextColor(30, 41, 59);
    $this->SetFont('Arial', 'B', 10);
    $this->SetXY(12, 50);
    $this->Cell(40, 5, $this->u('PROVEEDOR'), 0, 1, 'L');

    $this->SetFont('Arial', '', 9.6);
    $this->SetXY(12, 54.5);
    $this->Cell(125, 4.6, $this->u("Nombre: ".$this->proveedor["nombre"]), 0, 1, 'L');
    $this->SetX(12);
    $this->Cell(125, 4.6, $this->u("Documento: ".$this->proveedor["documento"]), 0, 1, 'L');
    $this->SetX(12);
    $this->Cell(125, 4.6, $this->u("Direccion: ".$this->proveedor["direccion"]), 0, 1, 'L');
    $this->SetX(12);
    $this->Cell(125, 4.6, $this->u("Email: ".$this->proveedor["email"]."  |  Telefono: ".$this->proveedor["telefono"]), 0, 1, 'L');
  }

  public function Footer()
  {
    $this->SetY(-12);
    $this->SetFont('Arial', 'I', 8);
    $this->SetTextColor(100, 116, 139);
    $this->Cell(0, 5, $this->u('Reporte de compra generado por el sistema'), 0, 0, 'L');
    $this->Cell(0, 5, $this->u('Pagina ').$this->PageNo(), 0, 0, 'R');
  }

  public function SetWidths($w)
  {
    $this->widths = $w;
  }

  public function SetAligns($a)
  {
    $this->aligns = $a;
  }

  public function CheckPageBreak($h)
  {
    if ($this->GetY() + $h > $this->PageBreakTrigger) {
      $this->AddPage($this->CurOrientation);
      $this->DrawTableHeader();
    }
  }

  public function NbLines($w, $txt)
  {
    $cw = &$this->CurrentFont['cw'];
    if ($w == 0) {
      $w = $this->w - $this->rMargin - $this->x;
    }
    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
    $s = str_replace("\r", '', (string)$txt);
    $nb = strlen($s);
    if ($nb > 0 && $s[$nb - 1] == "\n") {
      $nb--;
    }
    $sep = -1;
    $i = 0;
    $j = 0;
    $l = 0;
    $nl = 1;
    while ($i < $nb) {
      $c = $s[$i];
      if ($c == "\n") {
        $i++;
        $sep = -1;
        $j = $i;
        $l = 0;
        $nl++;
        continue;
      }
      if ($c == ' ') {
        $sep = $i;
      }
      $l += $cw[$c];
      if ($l > $wmax) {
        if ($sep == -1) {
          if ($i == $j) {
            $i++;
          }
        } else {
          $i = $sep + 1;
        }
        $sep = -1;
        $j = $i;
        $l = 0;
        $nl++;
      } else {
        $i++;
      }
    }
    return $nl;
  }

  public function DrawTableHeader()
  {
    $headers = array('CODIGO', 'DESCRIPCION', 'CANTIDAD', 'P.COMPRA', 'P.VENTA', 'SUBTOTAL');
    $this->SetFont('Arial', 'B', 9.4);
    $this->SetTextColor(255, 255, 255);
    $this->SetFillColor(2, 132, 199);
    for ($i = 0; $i < count($headers); $i++) {
      $this->Cell($this->widths[$i], 8, $this->u($headers[$i]), 1, 0, 'C', true);
    }
    $this->Ln();
    $this->SetTextColor(17, 24, 39);
    $this->SetFont('Arial', '', 9.2);
  }

  public function Row($data, $fill = false)
  {
    $nb = 0;
    for ($i = 0; $i < count($data); $i++) {
      $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
    }
    $h = 6 * $nb;
    $this->CheckPageBreak($h);

    for ($i = 0; $i < count($data); $i++) {
      $w = $this->widths[$i];
      $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
      $x = $this->GetX();
      $y = $this->GetY();

      if ($fill) {
        $this->SetFillColor(248, 250, 252);
        $this->Rect($x, $y, $w, $h, 'F');
      }
      $this->Rect($x, $y, $w, $h, 'D');
      $this->MultiCell($w, 6, $data[$i], 0, $a);
      $this->SetXY($x + $w, $y);
    }
    $this->Ln($h);
  }
}

$ingreso = new Ingreso();
$rspta = $ingreso->ingresocabecera($_GET["id"]);
$reg = $rspta->fetch_object();

if (!$reg) {
  echo "No se encontró el ingreso solicitado.";
  ob_end_flush();
  exit;
}

$empresaModel = new Empresa();
$empresa = $empresaModel->datosReporte();
$codigoMoneda = !empty($empresa["moneda"]) ? strtoupper((string)$empresa["moneda"]) : 'PEN';
$simboloMoneda = obtenerSimboloMoneda($codigoMoneda);
$nombreMonedaLetras = obtenerNombreMonedaLetras($codigoMoneda);

function formatearFechaComprobante($fechaRaw) {
  $fechaRaw = trim((string)$fechaRaw);
  if ($fechaRaw === '') {
    return '-';
  }

  if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fechaRaw)) {
    return $fechaRaw;
  }

  $formatos = array('Y-m-d H:i:s', 'Y-m-d', 'd-m-Y', 'd/m/Y H:i:s');
  foreach ($formatos as $formato) {
    $dt = DateTime::createFromFormat($formato, $fechaRaw);
    if ($dt instanceof DateTime) {
      return $dt->format('d/m/Y');
    }
  }

  $timestamp = strtotime($fechaRaw);
  if ($timestamp !== false) {
    return date('d/m/Y', $timestamp);
  }

  return $fechaRaw;
}

$logo = "";
if (!empty($empresa["logo"])) {
  $logoEmpresa = realpath(__DIR__."/../files/empresa/".$empresa["logo"]);
  if ($logoEmpresa && file_exists($logoEmpresa)) {
    $logo = $logoEmpresa;
  }
}
if ($logo === "") {
  $logo = __DIR__."/logo1.jpeg";
  if (!file_exists($logo)) {
    $logo = __DIR__."/logo.png";
  }
}

$documento = array(
  "titulo" => $reg->tipo_comprobante." N° ".$reg->serie_comprobante."-".$reg->num_comprobante,
  "fecha" => formatearFechaComprobante($reg->fecha)
);

$proveedor = array(
  "nombre" => $reg->proveedor,
  "documento" => $reg->tipo_documento.": ".$reg->num_documento,
  "direccion" => empty($reg->direccion) ? "-" : $reg->direccion,
  "email" => empty($reg->email) ? "-" : $reg->email,
  "telefono" => empty($reg->telefono) ? "-" : $reg->telefono
);

$pdf = new PDFCompra('P', 'mm', 'A4');
$pdf->empresa = $empresa;
$pdf->documento = $documento;
$pdf->proveedor = $proveedor;
$pdf->logo = $logo;

$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(190, 7, $pdf->u('DETALLE DE COMPRA'), 0, 1, 'L');

$pdf->SetWidths(array(24, 70, 24, 24, 24, 24));
$pdf->SetAligns(array('L', 'L', 'C', 'R', 'R', 'R'));
$pdf->DrawTableHeader();

$rsptad = $ingreso->ingresodetalles($_GET["id"]);
$index = 0;
while ($regd = $rsptad->fetch_object()) {
  $cantidad = number_format((float)$regd->cantidad, 3)." ".(empty($regd->unidad) ? "und" : $regd->unidad);
  $linea = array(
    $pdf->u($regd->codigo),
    $pdf->u($regd->articulo),
    $pdf->u($cantidad),
    number_format((float)$regd->precio_compra, 2),
    number_format((float)$regd->precio_venta, 2),
    number_format((float)$regd->subtotal, 2)
  );
  $pdf->Row($linea, ($index % 2) === 0);
  $index++;
}

$impuesto = (float)$reg->impuesto;
$total = (float)$reg->total_compra;
$factor = 1 + ($impuesto / 100);
if ($factor <= 0) {
  $factor = 1;
}
$subtotal = $total / $factor;
$igv = $total - $subtotal;

$V = new EnLetras();
$V->substituir_un_mil_por_mil = true;
$con_letra = strtoupper(trim($V->ValorEnLetras(round($total, 2), " ".$nombreMonedaLetras)));
$con_letra = preg_replace('/\s+/', ' ', str_replace('--', '', $con_letra));

if ($pdf->GetY() > 228) {
  $pdf->AddPage();
}

$startY = $pdf->GetY() + 6;

$pdf->SetDrawColor(214, 223, 233);
$pdf->SetFillColor(248, 250, 252);
$pdf->Rect(10, $startY, 122, 24, 'DF');
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetXY(13, $startY + 2.5);
$pdf->Cell(116, 5, $pdf->u('TOTAL EN LETRAS'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 9.2);
$pdf->SetXY(13, $startY + 8);
$pdf->MultiCell(116, 5, $pdf->u($con_letra." CON 00/100"), 0, 'L');

$boxX = 137;
$boxW = 63;
$pdf->SetFillColor(248, 250, 252);
$pdf->Rect($boxX, $startY, $boxW, 24, 'DF');
$pdf->SetFillColor(2, 132, 199);
$pdf->SetTextColor(255, 255, 255);
$pdf->Rect($boxX, $startY, $boxW, 6, 'F');
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetXY($boxX, $startY + 1.2);
$pdf->Cell($boxW, 4, $pdf->u('RESUMEN'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 9.2);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetXY($boxX + 2, $startY + 8);
$pdf->Cell(30, 4.4, 'SUBTOTAL', 0, 0, 'L');
$pdf->Cell(29, 4.4, $simboloMoneda.' '.number_format($subtotal, 2), 0, 1, 'R');
$pdf->SetX($boxX + 2);
$pdf->Cell(30, 4.4, 'IGV ('.number_format($impuesto, 2).'%)', 0, 0, 'L');
$pdf->Cell(29, 4.4, $simboloMoneda.' '.number_format($igv, 2), 0, 1, 'R');
$pdf->SetX($boxX + 2);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 4.8, 'TOTAL', 0, 0, 'L');
$pdf->Cell(29, 4.8, $simboloMoneda.' '.number_format($total, 2), 0, 1, 'R');

$nombreSalida = 'Compra_'.$reg->serie_comprobante.'-'.$reg->num_comprobante.'.pdf';
$pdf->Output($nombreSalida, 'I');

ob_end_flush();
?>
