<?php
require "../config/Conexion.php";

class Unidad{
	public function __construct(){
	}

	public function insertar($nombre, $abreviatura, $descripcion){
		$sql="INSERT INTO unidad_medida (nombre,abreviatura,descripcion,condicion) VALUES ('$nombre','$abreviatura','$descripcion','1')";
		return ejecutarConsulta($sql);
	}

	public function editar($idunidad, $nombre, $abreviatura, $descripcion){
		$sql="UPDATE unidad_medida SET nombre='$nombre',abreviatura='$abreviatura',descripcion='$descripcion' WHERE idunidad='$idunidad'";
		return ejecutarConsulta($sql);
	}

	public function desactivar($idunidad){
		$sql="UPDATE unidad_medida SET condicion='0' WHERE idunidad='$idunidad'";
		return ejecutarConsulta($sql);
	}

	public function activar($idunidad){
		$sql="UPDATE unidad_medida SET condicion='1' WHERE idunidad='$idunidad'";
		return ejecutarConsulta($sql);
	}

	public function mostrar($idunidad){
		$sql="SELECT * FROM unidad_medida WHERE idunidad='$idunidad'";
		return ejecutarConsultaSimpleFila($sql);
	}

	public function listar(){
		$sql="SELECT * FROM unidad_medida ORDER BY nombre ASC";
		return ejecutarConsulta($sql);
	}

	public function select(){
		$sql="SELECT * FROM unidad_medida WHERE condicion=1 ORDER BY nombre ASC";
		return ejecutarConsulta($sql);
	}
}

?>
