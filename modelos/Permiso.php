<?php
// Modelo de permisos (solo lectura, sin datos externos).
require_once "../config/Conexion.php";

class Permiso{

	public function __construct(){
	}

	// Listado de permisos (mysqli_result).
	public function listar(){
		return dbQuery("SELECT idpermiso,nombre FROM permiso ORDER BY idpermiso ASC");
	}
}
