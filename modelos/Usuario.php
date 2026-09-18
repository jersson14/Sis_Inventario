<?php
// Modelo de usuarios: consultas preparadas. Nunca expone el hash de la clave
// salvo en obtenerPorLogin/obtenerHashClave (uso exclusivo de autenticacion).
require_once "../config/Conexion.php";

class Usuario{

	public function __construct(){
	}

	// Tipos de documento admitidos.
	public static function tiposDocumento(){
		return array('DNI', 'RUC', 'CEDULA', 'PASAPORTE', 'OTRO');
	}

	// Registrar usuario. $claveHash ya debe venir hasheada. Devuelve el id generado (0 si falla).
	public function insertar($nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email,$cargo,$login,$claveHash,$imagen){
		$sql="INSERT INTO usuario (nombre,tipo_documento,num_documento,direccion,telefono,email,cargo,login,clave,imagen,condicion)
			VALUES (?,?,?,?,?,?,?,?,?,?,1)";
		return dbInsert($sql, array(
			(string)$nombre, (string)$tipo_documento, (string)$num_documento, (string)$direccion,
			(string)$telefono, (string)$email, (string)$cargo, (string)$login, (string)$claveHash, (string)$imagen
		));
	}

	// Actualizar usuario. Si $claveHash es '' no se modifica la clave. Devuelve true/false.
	public function editar($idusuario,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email,$cargo,$login,$claveHash,$imagen){
		$params = array(
			(string)$nombre, (string)$tipo_documento, (string)$num_documento, (string)$direccion,
			(string)$telefono, (string)$email, (string)$cargo, (string)$login, (string)$imagen
		);
		$sql="UPDATE usuario SET nombre=?,tipo_documento=?,num_documento=?,direccion=?,telefono=?,email=?,cargo=?,login=?,imagen=?";
		if ((string)$claveHash !== '') {
			$sql .= ",clave=?";
			$params[] = (string)$claveHash;
		}
		$sql .= " WHERE idusuario=?";
		$params[] = (int)$idusuario;
		return dbExec($sql, $params);
	}

	public function desactivar($idusuario){
		return dbExec("UPDATE usuario SET condicion=0 WHERE idusuario=?", array((int)$idusuario));
	}

	public function activar($idusuario){
		return dbExec("UPDATE usuario SET condicion=1 WHERE idusuario=?", array((int)$idusuario));
	}

	// Fila del usuario SIN el hash de clave (array asociativo) o null.
	public function mostrar($idusuario){
		return dbRow(
			"SELECT idusuario,nombre,tipo_documento,num_documento,direccion,telefono,email,cargo,login,imagen,condicion,ultimo_acceso,IFNULL(idalmacen,0) AS idalmacen
			 FROM usuario WHERE idusuario=?",
			array((int)$idusuario)
		);
	}

	// Usuario activo por login, incluye el hash de clave (solo para autenticacion). Array o null.
	public function obtenerPorLogin($login){
		return dbRow(
			"SELECT idusuario,nombre,tipo_documento,num_documento,telefono,email,cargo,imagen,login,clave,condicion
			 FROM usuario WHERE login=? AND condicion=1",
			array((string)$login)
		);
	}

	// Hash de clave actual de un usuario (solo para verificar cambio de clave). '' si no existe.
	public function obtenerHashClave($idusuario){
		return (string)dbValue("SELECT clave FROM usuario WHERE idusuario=?", array((int)$idusuario), '');
	}

	// Existe otro usuario con el mismo login (excluyendo $excluirId).
	public function existeLogin($login, $excluirId = 0){
		$n = dbValue("SELECT COUNT(*) FROM usuario WHERE login=? AND idusuario<>?", array((string)$login, (int)$excluirId), 0);
		return (int)$n > 0;
	}

	public function actualizarClave($idusuario, $hash){
		return dbExec("UPDATE usuario SET clave=? WHERE idusuario=?", array((string)$hash, (int)$idusuario));
	}

	public function registrarAcceso($idusuario){
		return dbExec("UPDATE usuario SET ultimo_acceso=NOW() WHERE idusuario=?", array((int)$idusuario));
	}

	// Listado SIN el hash de clave (mysqli_result).
	public function listar(){
		return dbQuery(
			"SELECT idusuario,nombre,tipo_documento,num_documento,direccion,telefono,email,cargo,login,imagen,condicion,ultimo_acceso,IFNULL(idalmacen,0) AS idalmacen
			 FROM usuario ORDER BY nombre ASC"
		);
	}

	// Permisos asignados a un usuario (mysqli_result con idpermiso).
	public function listarmarcados($idusuario){
		return dbQuery("SELECT idusuario_permiso,idusuario,idpermiso FROM usuario_permiso WHERE idusuario=?", array((int)$idusuario));
	}

	// Ids (int) de permisos asignados a un usuario.
	public function idsPermisos($idusuario){
		$ids = array();
		foreach (dbAll("SELECT idpermiso FROM usuario_permiso WHERE idusuario=?", array((int)$idusuario)) as $r) {
			$ids[] = (int)$r['idpermiso'];
		}
		return $ids;
	}

	// Nombres de los permisos asignados a un usuario.
	public function nombresPermisos($idusuario){
		$nombres = array();
		$filas = dbAll(
			"SELECT p.nombre FROM usuario_permiso up INNER JOIN permiso p ON up.idpermiso=p.idpermiso WHERE up.idusuario=? ORDER BY p.idpermiso ASC",
			array((int)$idusuario)
		);
		foreach ($filas as $r) {
			$nombres[] = (string)$r['nombre'];
		}
		return $nombres;
	}

	// Ids (int) existentes en la tabla permiso.
	public function permisosValidos(){
		$ids = array();
		foreach (dbAll("SELECT idpermiso FROM permiso") as $r) {
			$ids[] = (int)$r['idpermiso'];
		}
		return $ids;
	}

	// Reemplaza los permisos del usuario por $ids (ya validados). Devuelve true/false.
	// Pensado para ejecutarse dentro de dbTransaccion().
	public function reemplazarPermisos($idusuario, array $ids){
		$idusuario = (int)$idusuario;
		if (!dbExec("DELETE FROM usuario_permiso WHERE idusuario=?", array($idusuario))) {
			return false;
		}
		$ids = array_values(array_unique(array_map('intval', $ids)));
		foreach ($ids as $idpermiso) {
			if ($idpermiso <= 0) {
				continue;
			}
			if (!dbExec("INSERT INTO usuario_permiso (idusuario,idpermiso) VALUES (?,?)", array($idusuario, $idpermiso))) {
				return false;
			}
		}
		return true;
	}
}
