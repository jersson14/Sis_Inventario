<?php
require_once "../config/Conexion.php";

class Auditoria
{
	public function listar($fecha_inicio, $fecha_fin, $modulo = '', $idusuario = 0, $limite = 500)
	{
		$where = array("1=1");
		$params = array();
		if ($fecha_inicio !== '') {
			$where[] = "DATE(a.fecha_hora) >= ?";
			$params[] = $fecha_inicio;
		}
		if ($fecha_fin !== '') {
			$where[] = "DATE(a.fecha_hora) <= ?";
			$params[] = $fecha_fin;
		}
		if ($modulo !== '') {
			$where[] = "a.modulo = ?";
			$params[] = $modulo;
		}
		if ((int)$idusuario > 0) {
			$where[] = "a.idusuario = ?";
			$params[] = (int)$idusuario;
		}
		$limite = (int)$limite;
		if ($limite <= 0 || $limite > 5000) {
			$limite = 500;
		}
		return dbQuery("SELECT a.idauditoria, a.fecha_hora, a.usuario, a.modulo, a.accion, a.detalle, a.ip, IFNULL(u.nombre, a.usuario) AS nombre_usuario
			FROM auditoria a
			LEFT JOIN usuario u ON u.idusuario=a.idusuario
			WHERE " . implode(" AND ", $where) . "
			ORDER BY a.idauditoria DESC
			LIMIT " . $limite, $params);
	}

	public function modulos()
	{
		return dbAll("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo ASC");
	}

	public function usuarios()
	{
		return dbAll("SELECT idusuario, nombre, login FROM usuario ORDER BY nombre ASC");
	}

	public function intentosLogin($limite = 100)
	{
		$limite = (int)$limite;
		if ($limite <= 0) $limite = 100;
		return dbQuery("SELECT login, ip, exito, fecha_hora FROM intento_login ORDER BY idintento DESC LIMIT " . $limite);
	}
}
