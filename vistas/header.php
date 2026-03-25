<?php 
if (strlen(session_id())<1) 
  session_start();

$brandNombre = "PERNO CENTRO";
$brandSub = "SEÑOR DE HUANCA";
$brandLogo = "logo1.jpeg";
$brandPrimary = "#0f766e";
$brandPrimaryDark = "#0b4f4a";
$brandSecondary = "#f59e0b";

try {
  require_once "../config/Conexion.php";
if (!function_exists('darkenHexColor')) {
    function darkenHexColor($hex, $factor = 0.22) {
      $hex = ltrim((string)$hex, '#');
      if (strlen($hex) !== 6) {
        return '#0b4f4a';
      }
      $r = hexdec(substr($hex, 0, 2));
      $g = hexdec(substr($hex, 2, 2));
      $b = hexdec(substr($hex, 4, 2));
      $r = max(0, (int)round($r * (1 - $factor)));
      $g = max(0, (int)round($g * (1 - $factor)));
      $b = max(0, (int)round($b * (1 - $factor)));
      return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
  }
  if (!function_exists('decodeBrandText')) {
    function decodeBrandText($text) {
      $decoded = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      return trim($decoded);
    }
  }
  $tablaCfg = ejecutarConsulta("SHOW TABLES LIKE 'configuracion_empresa'");
  if ($tablaCfg && $tablaCfg->num_rows > 0) {
    $cfg = ejecutarConsultaSimpleFila("SELECT * FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
    if ($cfg) {
      if (!empty($cfg['nombre_comercial'])) {
        $brandNombre = strtoupper(decodeBrandText($cfg['nombre_comercial']));
      }
      if (!empty($cfg['razon_social'])) {
        $brandSub = strtoupper(decodeBrandText($cfg['razon_social']));
      }
      if ($brandSub === $brandNombre) {
        $brandSub = "";
      } elseif ($brandSub !== "" && stripos($brandSub, $brandNombre) !== false) {
        $resto = trim(str_ireplace($brandNombre, "", $brandSub));
        $resto = trim($resto, " -|\"'");
        if ($resto !== "") {
          $brandSub = $resto;
        }
      }
      if (!empty($cfg['logo'])) {
        $logoEmpresaFS = realpath(__DIR__ . "/../files/empresa/" . $cfg['logo']);
        if ($logoEmpresaFS && file_exists($logoEmpresaFS)) {
          $brandLogo = "../files/empresa/" . $cfg['logo'];
        } elseif (file_exists(__DIR__ . "/" . $cfg['logo'])) {
          $brandLogo = $cfg['logo'];
        } else {
          $brandLogo = "logo1.jpeg";
        }
      }
      if (!empty($cfg['color_primario'])) {
        $brandPrimary = $cfg['color_primario'];
        $brandPrimaryDark = darkenHexColor($brandPrimary, 0.25);
      }
      if (!empty($cfg['color_secundario'])) {
        $brandSecondary = $cfg['color_secundario'];
      }
    }
  }
} catch (Exception $e) {
}

  ?>
 <!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>SISVentas | Escritorio</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <!-- Bootstrap 3.3.7 -->
  <link rel="stylesheet" href="../public/css/bootstrap.min.css">
  <!-- Font Awesome -->

  <link rel="stylesheet" href="../public/css/font-awesome.min.css">

  <link rel="stylesheet" href="../public/css/AdminLTE.min.css">
  <link rel="stylesheet" href="../public/css/_all-skins.min.css">
  <link rel="stylesheet" href="../public/css/custom-theme.css?v=20260321n">
  <!-- Morris chart --><!-- Daterange picker -->
 <link rel="stylesheet" href="img/apple-touch-ico.png">
 <link rel="stylesheet" href="img/favicon.ico">
<!-- DATATABLES-->
<link rel="icon" href="<?php echo htmlspecialchars($brandLogo); ?>" type="image/jpg">

<link rel="stylesheet" href="../public/datatables/jquery.dataTables.min.css">
<link rel="stylesheet" href="../public/datatables/buttons.dataTables.min.css">
<link rel="stylesheet" href="../public/datatables/responsive.dataTables.min.css">
<link rel="stylesheet" href="../public/css/bootstrap-select.min.css">
<style>
:root{
  --brand-primary: <?php echo htmlspecialchars($brandPrimary); ?>;
  --brand-primary-dark: <?php echo htmlspecialchars($brandPrimaryDark); ?>;
  --brand-accent: <?php echo htmlspecialchars($brandSecondary); ?>;
}
</style>

</head>
<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">

  <header class="main-header">
    <!-- Logo -->
    <a href="escritorio.php" class="logo app-brand-logo">
      <!-- mini logo for sidebar mini 50x50 pixels -->
      <span class="logo-mini">
        <img src="<?php echo htmlspecialchars($brandLogo); ?>" alt="Logo Empresa" class="brand-logo-mini-img" onerror="this.src='logo1.jpeg'">
      </span>
      <!-- logo for regular state and mobile devices -->
      
      <span class="logo-lg">
        <span class="brand-logo-lg-wrap">
          <img src="<?php echo htmlspecialchars($brandLogo); ?>" alt="Logo Empresa" class="brand-logo-img" onerror="this.src='logo1.jpeg'">
          <span class="brand-logo-text">
            <strong><?php echo htmlspecialchars($brandNombre); ?></strong>
            <?php if (!empty($brandSub)) { ?>
            <small><?php echo htmlspecialchars($brandSub); ?></small>
            <?php } ?>
          </span>
        </span>
      </span>
    </a>
    <!-- Header Navbar: style can be found in header.less -->
    <nav class="navbar navbar-static-top">
      <!-- Sidebar toggle button-->
      <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
        <span class="sr-only">NAVEGACIÓM</span>
      </a>

      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">

          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <img src="../files/usuarios/<?php echo $_SESSION['imagen']; ?>" class="user-image" alt="User Image">
              <span class="hidden-xs"><?php echo $_SESSION['nombre']; ?></span>
            </a>
            <ul class="dropdown-menu">
              <!-- User image -->
              <li class="user-header">
                <img src="../files/usuarios/<?php echo $_SESSION['imagen']; ?>" class="img-circle" alt="User Image">

                <p>
                <span class="hidden-xs"><?php echo $_SESSION['nombre']; ?></span>
                  <small>2021</small>
                </p>
              </li>
              <!-- Menu Footer-->
              <li class="user-footer">
                <div class="pull-left">
                  <a href="usuario.php?perfil=1&idusuario=<?php echo (int)$_SESSION['idusuario']; ?>" class="btn btn-default btn-flat">Mi Perfil</a>
                </div>
                <div class="pull-right">
                  <a href="../ajax/usuario.php?op=salir" class="btn btn-default btn-flat">Salir</a>
                </div>
              </li>
            </ul>
          </li>
          <!-- Control Sidebar Toggle Button -->

        </ul>
      </div>
    </nav>
  </header>
  <!-- Left side column. contains the logo and sidebar -->
  <aside class="main-sidebar">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
      <!-- Sidebar user panel -->
     
      <!-- /.search form -->
      <!-- sidebar menu: : style can be found in sidebar.less -->
      <ul class="sidebar-menu" data-widget="tree">

<br>
       <?php 
if ($_SESSION['escritorio']==1) {
  echo ' <li><a href="escritorio.php"><i class="fa  fa-dashboard (alias)"></i> <span>Escritorio</span></a>
        </li>';
}
        ?>
               <?php 
if ($_SESSION['almacen']==1) {
  echo ' <li class="treeview">
          <a href="#">
            <i class="fa fa-laptop"></i> <span>Almacen</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="articulo.php"><i class="fa fa-circle-o"></i> Articulos</a></li>
            <li><a href="categoria.php"><i class="fa fa-circle-o"></i> Categorias</a></li>
            <li><a href="unidad.php"><i class="fa fa-circle-o"></i> Unidades</a></li>
          </ul>
        </li>';
}
        ?>
               <?php 
if ($_SESSION['compras']==1) {
  echo ' <li class="treeview">
          <a href="#">
            <i class="fa fa-th"></i> <span>Compras</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="ingreso.php"><i class="fa fa-circle-o"></i> Ingresos</a></li>
            <li><a href="proveedor.php"><i class="fa fa-circle-o"></i> Proveedores</a></li>
          </ul>
        </li>';
}
        ?>
        
               <?php 
if ($_SESSION['ventas']==1) {
  echo '<li class="treeview">
          <a href="#">
            <i class="fa fa-shopping-cart"></i> <span>Ventas</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="venta.php"><i class="fa fa-circle-o"></i> ventas</a></li>
            <li><a href="cliente.php"><i class="fa fa-circle-o"></i> clientes</a></li>
          </ul>
        </li>';
}
        ?>

                             <?php 
if ($_SESSION['acceso']==1) {
  echo '  <li class="treeview">
          <a href="#">
            <i class="fa fa-folder"></i> <span>Acceso</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="usuario.php"><i class="fa fa-circle-o"></i> Usuarios</a></li>
            <li><a href="permiso.php"><i class="fa fa-circle-o"></i> Permisos</a></li>
          </ul>
        </li>';
}
        ?>  
                                     <?php 
if ($_SESSION['consultac']==1) {
  echo '     <li class="treeview">
          <a href="#">
            <i class="fa fa-bar-chart"></i> <span>Consulta Compras</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="comprasfecha.php"><i class="fa fa-circle-o"></i>Compras por fechas</a></li>
          </ul>
        </li>';
}
        ?>  
              
                                                <?php 
if ($_SESSION['consultav']==1) {
  echo '<li class="treeview">
          <a href="#">
            <i class="fa fa-bar-chart"></i> <span>Consulta Ventas</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="ventasfechacliente.php"><i class="fa fa-circle-o"></i> Consulta Ventas</a></li>

          </ul>
        </li>';
}
        ?>     
             <?php
if ($_SESSION['acceso']==1 || $_SESSION['almacen']==1 || $_SESSION['compras']==1 || $_SESSION['ventas']==1) {
  echo '<li class="treeview">
          <a href="#">
            <i class="fa fa-rocket"></i> <span>Gestion Pro</span>
            <span class="pull-right-container">
              <i class="fa fa-angle-left pull-right"></i>
            </span>
          </a>
          <ul class="treeview-menu">
            <li><a href="empresa.php"><i class="fa fa-circle-o"></i> Empresa</a></li>
            <li><a href="procenter.php"><i class="fa fa-circle-o"></i> Kardex y Alertas</a></li>
            <li><a href="cuentas.php"><i class="fa fa-circle-o"></i> CxC y CxP</a></li>
            <li><a href="caja.php"><i class="fa fa-circle-o"></i> Caja diaria</a></li>
            <li><a href="backup.php"><i class="fa fa-circle-o"></i> Backup</a></li>
          </ul>
        </li>';
}
        ?>
             
        
        
      </ul>
    </section>
    <!-- /.sidebar -->
  </aside>
