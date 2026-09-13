<?php
// Modelo de personas (clientes y proveedores): consultas preparadas y baja logica.
require_once "../config/Conexion.php";

class Persona{

	public function __construct(){
	}

	// Tipos de persona admitidos.
	public static function tiposPersona(){
		return array('Cliente', 'Proveedor');
	}

	// Tipos de documento admitidos.
	public static function tiposDocumento(){
		return array('DNI', 'RUC', 'CEDULA', 'PASAPORTE', 'OTRO');
	}

	// Registrar persona. Devuelve true/false.
	public function insertar($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email){
		return $this->insertarRetornarId($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email) > 0;
	}

	// Registrar persona y devolver el id generado (0 si falla). Lo usan los modulos de venta e ingreso.
	public function insertarRetornarId($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email){
		if (!in_array((string)$tipo_persona, self::tiposPersona(), true)) {
			return 0;
		}
		$sql="INSERT INTO persona (tipo_persona,nombre,tipo_documento,num_documento,direccion,telefono,email,condicion) VALUES (?,?,?,?,?,?,?,1)";
		return dbInsert($sql, array(
			(string)$tipo_persona, (string)$nombre, (string)$tipo_documento, (string)$num_documento,
			(string)$direccion, (string)$telefono, (string)$email
		));
	}

	// Actualizar persona. Devuelve true/false.
	public function editar($idpersona,$tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email){
		if (!in_array((string)$tipo_persona, self::tiposPersona(), true)) {
			return false;
		}
		$sql="UPDATE persona SET tipo_persona=?,nombre=?,tipo_documento=?,num_documento=?,direccion=?,telefono=?,email=? WHERE idpersona=?";
		return dbExec($sql, array(
			(string)$tipo_persona, (string)$nombre, (string)$tipo_documento, (string)$num_documento,
			(string)$direccion, (string)$telefono, (string)$email, (int)$idpersona
		));
	}

	// Baja logica.
	public function desactivar($idpersona){
		return dbExec("UPDATE persona SET condicion=0 WHERE idpersona=?", array((int)$idpersona));
	}

	// Compatibilidad: "eliminar" ahora es baja logica.
	public function eliminar($idpersona){
		return $this->desactivar($idpersona);
	}

	public function activar($idpersona){
		return dbExec("UPDATE persona SET condicion=1 WHERE idpersona=?", array((int)$idpersona));
	}

	// Devuelve la fila (array asociativo) o null.
	public function mostrar($idpersona){
		return dbRow("SELECT idpersona,tipo_persona,nombre,tipo_documento,num_documento,direccion,telefono,email,condicion FROM persona WHERE idpersona=?", array((int)$idpersona));
	}

	// Existe otra persona del mismo tipo con el mismo numero de documento (excluyendo $excluirId).
	public function existeDocumento($tipo_persona, $num_documento, $excluirId = 0){
		$n = dbValue(
			"SELECT COUNT(*) FROM persona WHERE tipo_persona=? AND num_documento=? AND idpersona<>?",
			array((string)$tipo_persona, (string)$num_documento, (int)$excluirId),
			0
		);
		return (int)$n > 0;
	}

	// Proveedores (activos e inactivos), mysqli_result.
	public function listarp(){
		return dbQuery("SELECT idpersona,tipo_persona,nombre,tipo_documento,num_documento,direccion,telefono,email,condicion FROM persona WHERE tipo_persona='Proveedor' ORDER BY nombre ASC");
	}

	// Clientes (activos e inactivos), mysqli_result.
	public function listarc(){
		return dbQuery("SELECT idpersona,tipo_persona,nombre,tipo_documento,num_documento,direccion,telefono,email,condicion FROM persona WHERE tipo_persona='Cliente' ORDER BY nombre ASC");
	}

	// Clientes activos para selects, mysqli_result.
	public function selectClientes(){
		return dbQuery("SELECT idpersona,nombre,num_documento FROM persona WHERE tipo_persona='Cliente' AND condicion=1 ORDER BY nombre ASC");
	}

	// Proveedores activos para selects, mysqli_result.
	public function selectProveedores(){
		return dbQuery("SELECT idpersona,nombre,num_documento FROM persona WHERE tipo_persona='Proveedor' AND condicion=1 ORDER BY nombre ASC");
	}
}
