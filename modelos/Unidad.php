<?php
// Modelo de unidades de medida: consultas preparadas.
require_once "../config/Conexion.php";

class Unidad{

	public function __construct(){
	}

	public function insertar($nombre, $abreviatura, $descripcion, $permiteFraccion = 0){
		return dbExec("INSERT INTO unidad_medida (nombre,abreviatura,descripcion,permite_fraccion,condicion) VALUES (?,?,?,?,1)", array((string)$nombre, (string)$abreviatura, (string)$descripcion, $permiteFraccion ? 1 : 0));
	}

	public function editar($idunidad, $nombre, $abreviatura, $descripcion, $permiteFraccion = 0){
		return dbExec("UPDATE unidad_medida SET nombre=?,abreviatura=?,descripcion=?,permite_fraccion=? WHERE idunidad=?", array((string)$nombre, (string)$abreviatura, (string)$descripcion, $permiteFraccion ? 1 : 0, (int)$idunidad));
	}

	public function desactivar($idunidad){
		return dbExec("UPDATE unidad_medida SET condicion=0 WHERE idunidad=?", array((int)$idunidad));
	}

	public function activar($idunidad){
		return dbExec("UPDATE unidad_medida SET condicion=1 WHERE idunidad=?", array((int)$idunidad));
	}

	// Devuelve la fila (array asociativo) o null.
	public function mostrar($idunidad){
		return dbRow("SELECT idunidad,nombre,abreviatura,descripcion,permite_fraccion,condicion FROM unidad_medida WHERE idunidad=?", array((int)$idunidad));
	}

	// Existe otra unidad con el mismo nombre (excluyendo $excluirId).
	public function existeNombre($nombre, $excluirId = 0){
		$n = dbValue("SELECT COUNT(*) FROM unidad_medida WHERE nombre=? AND idunidad<>?", array((string)$nombre, (int)$excluirId), 0);
		return (int)$n > 0;
	}

	// Existe otra unidad con la misma abreviatura (excluyendo $excluirId).
	public function existeAbreviatura($abreviatura, $excluirId = 0){
		$n = dbValue("SELECT COUNT(*) FROM unidad_medida WHERE abreviatura=? AND idunidad<>?", array((string)$abreviatura, (int)$excluirId), 0);
		return (int)$n > 0;
	}

	// Listado completo (mysqli_result).
	public function listar(){
		return dbQuery("SELECT idunidad,nombre,abreviatura,descripcion,permite_fraccion,condicion FROM unidad_medida ORDER BY nombre ASC");
	}

	// Solo activas, para selects (mysqli_result).
	public function select(){
		return dbQuery("SELECT idunidad,nombre,abreviatura,permite_fraccion FROM unidad_medida WHERE condicion=1 ORDER BY nombre ASC");
	}
}
