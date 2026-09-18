<?php
require_once "../config/seguridad.php";
iniciarSesionSegura();
enviarCabecerasSeguridad();

if (usuarioAutenticado()) {
  header("Location: " . paginaInicioUsuario());
  exit;
}

$csrf = csrfToken();
$expirada = isset($_GET['expirada']) && $_GET['expirada'] == '1';
$salio = isset($_GET['salir']) && $_GET['salir'] == '1';

// Marca de la empresa (config/marca.php): la misma del panel y la landing
$marca = marcaEmpresa();
$brandNombre = $marca['nombre'];
$brandSub = $marca['sub'];
$brandLogo = marcaUrlLogo('../');
$brandPrimary = $marca['primario'];
$brandPrimaryDark = $marca['primario_oscuro'];
$brandSecondary = $marca['secundario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Iniciar sesión | <?php echo e($brandNombre); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="<?php echo e($brandLogo); ?>">
  <link rel="stylesheet" href="../public/css/font-awesome.min.css">
  <link rel="stylesheet" href="../public/css/login.css?v=<?php echo e(APP_VERSION); ?>">
  <style>
    :root{
      --brand-primary: <?php echo e($brandPrimary); ?>;
      --brand-primary-dark: <?php echo e($brandPrimaryDark); ?>;
      --brand-accent: <?php echo e($brandSecondary); ?>;
    }
  </style>
</head>
<body class="login-page">
<a class="login-back" href="../index.php" title="Volver al sitio"><i class="fa fa-arrow-left"></i> Sitio web</a>

<div class="login-shell">
  <section class="login-brand">
    <div class="login-brand-inner">
      <div class="login-brand-logo-wrap">
        <img src="<?php echo e($brandLogo); ?>" alt="Logo" class="login-brand-logo" onerror="this.src='../public/img/brand-store.svg'">
      </div>
      <h1><?php echo e($brandNombre); ?></h1>
      <?php if ($brandSub !== '') { ?><p class="login-brand-sub"><?php echo e($brandSub); ?></p><?php } ?>
      <p class="login-brand-desc">Inventario, compras, ventas, caja y cuentas en un solo panel. Control operativo en tiempo real.</p>
      <ul class="brand-bullets">
        <li><i class="fa fa-check-circle"></i> Stock al día con kardex y alertas</li>
        <li><i class="fa fa-check-circle"></i> Punto de venta rápido con lector de códigos</li>
        <li><i class="fa fa-check-circle"></i> Utilidad real por producto, categoría y vendedor</li>
        <li><i class="fa fa-check-circle"></i> Caja diaria, créditos y cuentas por cobrar</li>
      </ul>
      <div class="login-brand-foot">
        <span><i class="fa fa-shield"></i> Sesión cifrada y protegida</span>
      </div>
    </div>
  </section>

  <section class="login-form-panel">
    <div class="login-form-card">
      <h2 class="login-form-title">Bienvenido de nuevo</h2>
      <p class="login-form-subtitle">Ingresa tus credenciales para acceder al panel</p>

      <div id="loginAlert" class="login-alert" role="alert" <?php echo ($expirada || $salio) ? '' : 'hidden'; ?>>
        <?php if ($expirada) { ?><i class="fa fa-clock-o"></i> Tu sesión expiró por inactividad. Vuelve a ingresar.<?php } elseif ($salio) { ?><i class="fa fa-check-circle"></i> Cerraste sesión correctamente.<?php } ?>
      </div>

      <form method="post" id="frmAcceso" autocomplete="on" novalidate>
        <input type="hidden" name="_csrf" id="csrf" value="<?php echo e($csrf); ?>">
        <div class="form-group">
          <label for="logina">Usuario</label>
          <div class="input-wrap">
            <i class="fa fa-user"></i>
            <input type="text" id="logina" name="logina" class="form-control" placeholder="Tu nombre de usuario" required autocomplete="username" autocapitalize="off" spellcheck="false" maxlength="60">
          </div>
        </div>
        <div class="form-group">
          <label for="clavea">Contraseña</label>
          <div class="input-wrap">
            <i class="fa fa-lock"></i>
            <input type="password" id="clavea" name="clavea" class="form-control login-password-input" placeholder="Tu contraseña" required autocomplete="current-password">
            <button type="button" id="toggleClave" class="password-toggle-btn" aria-label="Mostrar contraseña" title="Mostrar contraseña"><i class="fa fa-eye"></i></button>
          </div>
        </div>
        <div class="login-options-row">
          <label class="login-remember">
            <input type="checkbox" id="rememberLogin">
            <span>Recordar mi usuario</span>
          </label>
        </div>
        <button type="submit" class="btn-login" id="btnLogin"><span class="btn-login-text"><i class="fa fa-sign-in"></i> Entrar al sistema</span><span class="btn-login-spinner"></span></button>
      </form>

      <p class="login-help">¿Olvidaste tu contraseña? Pide a un administrador que la restablezca desde <strong>Usuarios</strong>.</p>
      <p class="login-version"><?php echo e(PRO_NOMBRE); ?> v<?php echo e(APP_VERSION); ?></p>
    </div>
  </section>
</div>

<script src="../public/js/jquery.min.js?v=<?php echo e(APP_VERSION); ?>"></script>
<script src="scripts/login.js?v=<?php echo e(APP_VERSION); ?>"></script>
</body>
</html>
