<?php 
//activamos almacenamiento en el buffer
ob_start();
if (strlen(session_id())<1) 
  session_start();

if (!isset($_SESSION['nombre'])) {
  echo "debe ingresar al sistema correctamente para vosualizar el reporte";
}else{

if ($_SESSION['almacen']==1) {

//incluimos a la clase PDF_MC_Table
require('PDF_MC_Table.php');
require_once "../modelos/Empresa.php";
require_once "../modelos/Articulo.php";

$empresaModel = new Empresa();
$empresa = $empresaModel->datosReporte();

$logoPath = "";
if (!empty($empresa["logo"])) {
  $logoEmpresa = realpath(__DIR__."/../files/empresa/".$empresa["logo"]);
  if ($logoEmpresa && file_exists($logoEmpresa)) {
    $logoPath = $logoEmpresa;
  }
}
if ($logoPath === "") {
  if (file_exists(__DIR__."/logo1.jpeg")) {
    $logoPath = __DIR__."/logo1.jpeg";
  } elseif (file_exists(__DIR__."/logo.png")) {
    $logoPath = __DIR__."/logo.png";
  }
}

//instanciamos la clase para generar el documento pdf
$pdf=new PDF_MC_Table();

//agregamos la primera pagina al documento pdf
$pdf->AddPage();

if ($logoPath !== "") {
  $pdf->Image($logoPath, 10, 10, 22, 22);
}

$pdf->SetFont('Arial','B',11);
$pdf->SetXY(36,10);
$pdf->Cell(120,5,utf8_decode((string)$empresa["nombre"]),0,1,'L');
$pdf->SetFont('Arial','',9.5);
$pdf->SetX(36);
$pdf->Cell(120,4,'RUC: '.utf8_decode((string)$empresa["ruc"]),0,1,'L');
$pdf->SetX(36);
$pdf->Cell(120,4,utf8_decode((string)$empresa["direccion_linea1"]),0,1,'L');
if (!empty($empresa["direccion_linea2"])) {
  $pdf->SetX(36);
  $pdf->Cell(120,4,utf8_decode((string)$empresa["direccion_linea2"]),0,1,'L');
}
$pdf->SetX(36);
$pdf->Cell(120,4,'Tel: '.utf8_decode((string)$empresa["telefono"]).'  |  '.utf8_decode((string)$empresa["email"]),0,1,'L');

$pdf->SetY(36);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(190,8,'LISTA DE ARTICULOS',1,1,'C');
$pdf->Ln(2);

//creamos las celdas para los titulos de cada columna y le asignamos un fondo gris y el tipo de letra
$pdf->SetFillColor(232,232,232);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(58,6,'Nombre',1,0,'C',1);
$pdf->Cell(50,6,utf8_decode('Categoría'),1,0,'C',1);
$pdf->Cell(30,6,utf8_decode('Código'),1,0,'C',1);
$pdf->Cell(12,6,'Stock',1,0,'C',1);
$pdf->Cell(35,6,utf8_decode('Descripcion'),1,0,'C',1);
$pdf->Ln(10);

//creamos las filas de los registros según la consulta mysql
$articulo = new Articulo();

$rspta = $articulo->listar();

//implementamos las celdas de la tabla con los registros a mostrar
$pdf->SetWidths(array(58,50,30,12,35));

while ($reg= $rspta->fetch_object()) {
	$nombre=$reg->nombre;
	$categoria= $reg->categoria;
	$codigo=$reg->codigo;
	$stock=$reg->stock;
	$descripcion=$reg->descripcion;

	$pdf->SetFont('Arial','',10);
	$pdf->Row(array(utf8_decode($nombre),utf8_decode($categoria),$codigo,$stock,utf8_decode($descripcion)));
}

//mostramos el documento pdf
$pdf->Output();

}else{
echo "No tiene permiso para visualizar el reporte";
}

}

ob_end_flush();
  ?>
