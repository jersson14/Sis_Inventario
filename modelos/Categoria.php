<?php
// Modelo de categorias: consultas preparadas.
require_once "../config/Conexion.php";

class Categoria{

	public function __construct(){
	}

	public function insertar($nombre,$descripcion){
		return dbExec("INSERT INTO categoria (nombre,descripcion,condicion) VALUES (?,?,1)", array((string)$nombre, (string)$descripcion));
	}

	public function editar($idcategoria,$nombre,$descripcion){
		return dbExec("UPDATE categoria SET nombre=?,descripcion=? WHERE idcategoria=?", array((string)$nombre, (string)$descripcion, (int)$idcategoria));
	}

	public function desactivar($idcategoria){
		return dbExec("UPDATE categoria SET condicion=0 WHERE idcategoria=?", array((int)$idcategoria));
	}

	public function activar($idcategoria){
		return dbExec("UPDATE categoria SET condicion=1 WHERE idcategoria=?", array((int)$idcategoria));
	}

	// Devuelve la fila (array asociativo) o null.
	public function mostrar($idcategoria){
		return dbRow("SELECT idcategoria,nombre,descripcion,condicion FROM categoria WHERE idcategoria=?", array((int)$idcategoria));
	}

	// Existe otra categoria con el mismo nombre (excluyendo $excluirId).
	public function existeNombre($nombre, $excluirId = 0){
		$n = dbValue("SELECT COUNT(*) FROM categoria WHERE nombre=? AND idcategoria<>?", array((string)$nombre, (int)$excluirId), 0);
		return (int)$n > 0;
	}

	// Listado completo (mysqli_result).
	public function listar(){
		return dbQuery("SELECT idcategoria,nombre,descripcion,condicion FROM categoria ORDER BY nombre ASC");
	}

	// Solo activas, para selects (mysqli_result).
	public function select(){
		return dbQuery("SELECT idcategoria,nombre FROM categoria WHERE condicion=1 ORDER BY nombre ASC");
	}
}
