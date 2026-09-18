<?php
/**
 * Cabecera comun del panel. Requiere que la vista haya llamado antes a
 * requiereLogin(false). Variables opcionales definidas por la vista:
 *   $tituloPagina  (string)  Titulo mostrado en la barra y en <title>
 *   $iconoPagina   (string)  Clase FontAwesome, ej. "fa-cube"
 */
require_once __DIR__ . "/../config/seguridad.php";
if (session_status() !== PHP_SESSION_ACTIVE) {
  iniciarSesionSegura();
}
if (!usuarioAutenticado()) {
  header("Location: login.php?expirada=1");
  exit;
}
enviarCabecerasSeguridad();

$tituloPagina = isset($tituloPagina) ? $tituloPagina : 'Escritorio';
$iconoPagina  = isset($iconoPagina) ? $iconoPagina : 'fa-dashboard';

// Marca de la empresa (logo, nombre, colores): config/marca.php
$marca = marcaEmpresa();
$brandNombre = $marca['nombre'];
$brandSub = $marca['sub'];
$brandLogo = marcaUrlLogo('../');
$brandPrimary = $marca['primario'];
$brandPrimaryDark = $marca['primario_oscuro'];
$brandPrimarySoft = $marca['primario_suave'];
$brandSecondary = $marca['secundario'];
$brandSecondaryDark = $marca['secundario_oscuro'];
$paginaInicio = paginaInicioUsuario();

$avatarUsuario = "../public/img/avatar.png";
if (!empty($_SESSION['imagen'])) {
  $imgSeg = nombreArchivoSeguro($_SESSION['imagen']);
  if ($imgSeg !== '' && file_exists(__DIR__ . "/../files/usuarios/" . $imgSeg)) {
    $avatarUsuario = "../files/usuarios/" . $imgSeg;
  }
}


$menu = array(
  array('tipo' => 'item', 'href' => 'escritorio.php', 'icono' => 'fa-dashboard', 'texto' => 'Escritorio', 'permisos' => array('escritorio')),

  array('tipo' => 'header', 'texto' => 'Operaciones', 'permisos' => array('ventas', 'compras', 'caja', 'cuentas')),
  array('tipo' => 'grupo', 'icono' => 'fa-shopping-cart', 'texto' => 'Ventas', 'permisos' => array('ventas'), 'hijos' => array(
    array('href' => 'venta.php', 'texto' => 'Punto de venta'),
    array('href' => 'cotizacion.php', 'texto' => 'Cotizaciones'),
    array('href' => 'cliente.php', 'texto' => 'Clientes'),
  )),
  array('tipo' => 'grupo', 'icono' => 'fa-truck', 'texto' => 'Compras', 'permisos' => array('compras'), 'hijos' => array(
    array('href' => 'ingreso.php', 'texto' => 'Ingresos / Compras'),
    array('href' => 'proveedor.php', 'texto' => 'Proveedores'),
  )),
  array('tipo' => 'grupo', 'icono' => 'fa-money', 'texto' => 'Finanzas', 'permisos' => array('caja', 'cuentas', 'ventas', 'compras'), 'hijos' => array(
    array('href' => 'caja.php', 'texto' => 'Caja diaria', 'permisos' => array('caja', 'ventas')),
    array('href' => 'cuentas.php', 'texto' => 'Cuentas por cobrar / pagar', 'permisos' => array('cuentas')),
  )),

  array('tipo' => 'header', 'texto' => 'Inventario', 'permisos' => array('almacen', 'inventario', 'procenter')),
  array('tipo' => 'grupo', 'icono' => 'fa-cubes', 'texto' => 'Almacén', 'permisos' => array('almacen'), 'hijos' => array(
    array('href' => 'articulo.php', 'texto' => 'Artículos'),
    array('href' => 'categoria.php', 'texto' => 'Categorías'),
    array('href' => 'unidad.php', 'texto' => 'Unidades de medida'),
    array('href' => 'etiquetas.php', 'texto' => 'Etiquetas de código de barras'),
    array('href' => 'importar.php', 'texto' => 'Importar desde Excel'),
  )),
  array('tipo' => 'item', 'href' => 'inventario.php', 'icono' => 'fa-exchange', 'texto' => 'Ajustes de inventario', 'permisos' => array('inventario', 'almacen')),
  array('tipo' => 'item', 'href' => 'vencimientos.php', 'icono' => 'fa-calendar-times-o', 'texto' => 'Vencimientos', 'permisos' => array('inventario', 'almacen'), 'capacidades' => array('vencimientos', 'lotes')),
  array('tipo' => 'item', 'href' => 'procenter.php', 'icono' => 'fa-line-chart', 'texto' => 'Kardex y alertas', 'permisos' => array('procenter', 'almacen')),

  array('tipo' => 'header', 'texto' => 'Análisis', 'permisos' => array('reportes', 'consultac', 'consultav')),
  array('tipo' => 'grupo', 'icono' => 'fa-bar-chart', 'texto' => 'Reportes', 'permisos' => array('reportes', 'consultac', 'consultav'), 'hijos' => array(
    array('href' => 'reportes.php', 'texto' => 'Centro de reportes', 'permisos' => array('reportes', 'consultac', 'consultav')),
    array('href' => 'comprasfecha.php', 'texto' => 'Compras por fecha', 'permisos' => array('consultac')),
    array('href' => 'ventasfechacliente.php', 'texto' => 'Ventas por cliente', 'permisos' => array('consultav')),
  )),

  array('tipo' => 'header', 'texto' => 'Administración', 'permisos' => array('acceso', 'empresa', 'backup')),
  array('tipo' => 'grupo', 'icono' => 'fa-cog', 'texto' => 'Configuración', 'permisos' => array('acceso', 'empresa', 'backup'), 'hijos' => array(
    array('href' => 'usuario.php', 'texto' => 'Usuarios', 'permisos' => array('acceso')),
    array('href' => 'permiso.php', 'texto' => 'Permisos', 'permisos' => array('acceso')),
    array('href' => 'empresa.php', 'texto' => 'Empresa y marca', 'permisos' => array('empresa', 'acceso')),
    array('href' => 'backup.php', 'texto' => 'Backup y restauración', 'permisos' => array('backup', 'acceso')),
    array('href' => 'auditoria.php', 'texto' => 'Auditoría', 'permisos' => array('acceso')),
  )),
);

if (!function_exists('menuTienePermiso')) {
  function menuTienePermiso($permisos) {
    if (empty($permisos)) return true;
    foreach ($permisos as $p) {
      if (usuarioTienePermiso($p)) return true;
    }
    return false;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo e($tituloPagina); ?> | <?php echo e($brandNombre); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="<?php echo e($brandLogo); ?>">
  <link rel="stylesheet" href="../public/css/bootstrap.min.css?v=<?php echo e(APP_VERSION); ?>">
  <link rel="stylesheet" href="../public/css/font-awesome.min.css">
  <link rel="stylesheet" href="../public/css/AdminLTE.min.css">
  <link rel="stylesheet" href="../public/css/_all-skins.min.css">
  <link rel="stylesheet" href="../public/datatables/jquery.dataTables.min.css">
  <link rel="stylesheet" href="../public/datatables/buttons.dataTables.min.css">
  <link rel="stylesheet" href="../public/datatables/responsive.dataTables.min.css">
  <link rel="stylesheet" href="../public/css/bootstrap-select.min.css">
  <link rel="stylesheet" href="../public/css/custom-theme.css?v=<?php echo e(APP_VERSION); ?>">
  <style>
    :root{
      --brand-primary: <?php echo e($brandPrimary); ?>;
      --brand-primary-dark: <?php echo e($brandPrimaryDark); ?>;
      --brand-primary-soft: <?php echo e($brandPrimarySoft); ?>;
      --brand-accent: <?php echo e($brandSecondary); ?>;
      --brand-accent-dark: <?php echo e($brandSecondaryDark); ?>;
    }
  </style>
</head>
<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">

  <header class="main-header">
    <a href="<?php echo e($paginaInicio); ?>" class="logo app-brand-logo" title="<?php echo e($brandNombre); ?>">
      <span class="logo-mini">
        <img src="<?php echo e($brandLogo); ?>" alt="Logo" class="brand-logo-mini-img" onerror="this.src='../public/img/brand-store.svg'">
      </span>
      <span class="logo-lg">
        <span class="brand-logo-lg-wrap">
          <img src="<?php echo e($brandLogo); ?>" alt="Logo" class="brand-logo-img" onerror="this.src='../public/img/brand-store.svg'">
          <span class="brand-logo-text">
            <strong><?php echo e($brandNombre); ?></strong>
            <small><?php echo e($brandSub !== '' ? $brandSub : 'Sistema de gestión'); ?></small>
          </span>
        </span>
      </span>
    </a>

    <nav class="navbar navbar-static-top">
      <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button" title="Mostrar / ocultar menú">
        <span class="sr-only">Alternar navegación</span>
      </a>

      <div class="navbar-page-title"><i class="fa <?php echo e($iconoPagina); ?>"></i> <?php echo e($tituloPagina); ?></div>

      <div class="navbar-quick">
        <?php if (usuarioTienePermiso('ventas')) { ?>
        <a href="venta.php?nuevo=1" class="btn btn-primary btn-sm" title="Registrar una venta (Alt+V)"><i class="fa fa-plus"></i> Venta</a>
        <?php } ?>
        <?php if (usuarioTienePermiso('compras')) { ?>
        <a href="ingreso.php?nuevo=1" class="btn btn-default btn-sm" title="Registrar una compra (Alt+C)"><i class="fa fa-plus"></i> Compra</a>
        <?php } ?>
        <?php if (usuarioTienePermiso('almacen')) { ?>
        <a href="articulo.php?nuevo=1" class="btn btn-default btn-sm" title="Registrar un artículo (Alt+A)"><i class="fa fa-plus"></i> Artículo</a>
        <?php } ?>
      </div>

      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <?php if (usuarioTienePermiso('escritorio')) { ?>
          <li class="dropdown navbar-bell" id="navbarBell">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" title="Alertas">
              <i class="fa fa-bell-o"></i>
              <span class="bell-count" id="bellCount">0</span>
            </a>
            <ul class="dropdown-menu">
              <li class="bell-header">Alertas del negocio</li>
              <li><div id="bellItems"><div class="bell-empty"><i class="fa fa-spinner fa-spin"></i> Cargando…</div></div></li>
            </ul>
          </li>
          <?php } ?>
          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <img src="<?php echo e($avatarUsuario); ?>" class="user-image" alt="Usuario" onerror="this.src='../public/img/avatar.png'">
              <span class="user-menu-text hidden-xs">
                <strong><?php echo e($_SESSION['nombre']); ?></strong>
                <small><?php echo e(!empty($_SESSION['cargo']) ? $_SESSION['cargo'] : 'Usuario'); ?></small>
              </span>
              <i class="fa fa-angle-down hidden-xs" style="color:#94a3b8"></i>
            </a>
            <ul class="dropdown-menu">
              <li class="user-header">
                <img src="<?php echo e($avatarUsuario); ?>" class="img-circle" alt="Usuario" onerror="this.src='../public/img/avatar.png'">
                <p>
                  <?php echo e($_SESSION['nombre']); ?>
                  <small><?php echo e($_SESSION['login']); ?><?php echo !empty($_SESSION['cargo']) ? ' · ' . e($_SESSION['cargo']) : ''; ?></small>
                </p>
              </li>
              <li>
                <div class="user-menu-links">
                  <a href="usuario.php?perfil=1&idusuario=<?php echo (int)$_SESSION['idusuario']; ?>"><i class="fa fa-user"></i> Mi perfil</a>
                  <a href="usuario.php?perfil=1&idusuario=<?php echo (int)$_SESSION['idusuario']; ?>#cambiarClave"><i class="fa fa-key"></i> Cambiar contraseña</a>
                  <a href="../ajax/usuario.php?op=salir" class="text-danger"><i class="fa fa-sign-out"></i> Cerrar sesión</a>
                </div>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </nav>
  </header>

  <aside class="main-sidebar">
    <section class="sidebar">
      <ul class="sidebar-menu" data-widget="tree">
        <?php foreach ($menu as $item) {
          if (!menuTienePermiso($item['permisos'])) continue;
          // Opciones propias de un rubro (ej. Vencimientos solo en abarrotes)
          if (isset($item['capacidades']) && !array_filter($item['capacidades'], 'negocioTiene')) continue;
          if ($item['tipo'] === 'header') {
            echo '<li class="header">' . e($item['texto']) . '</li>';
          } elseif ($item['tipo'] === 'item') {
            echo '<li><a href="' . e($item['href']) . '"><i class="fa ' . e($item['icono']) . '"></i> <span>' . e($item['texto']) . '</span></a></li>';
          } else {
            $hijosHtml = '';
            foreach ($item['hijos'] as $h) {
              if (isset($h['permisos']) && !menuTienePermiso($h['permisos'])) continue;
              $hijosHtml .= '<li><a href="' . e($h['href']) . '"><i class="fa fa-circle"></i> ' . e($h['texto']) . '</a></li>';
            }
            if ($hijosHtml === '') continue;
            echo '<li class="treeview"><a href="#"><i class="fa ' . e($item['icono']) . '"></i> <span>' . e($item['texto']) . '</span><span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span></a><ul class="treeview-menu">' . $hijosHtml . '</ul></li>';
          }
        } ?>
      </ul>
      <div class="sidebar-footer-brand">
        <strong><?php echo e(PRO_NOMBRE); ?></strong> v<?php echo e(APP_VERSION); ?><br>
        Sesión: <?php echo e($_SESSION['login']); ?>
      </div>
    </section>
  </aside>
