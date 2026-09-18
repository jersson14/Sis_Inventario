<?php
/**
 * Landing page publica del producto.
 * Los datos de contacto salen de configuracion_empresa (correo / celular / web) si existen.
 */
require_once __DIR__ . "/config/seguridad.php";
iniciarSesionSegura();
enviarCabecerasSeguridad();

$producto = PRO_NOMBRE;
$contactoEmail = '';
$contactoCelular = '';
$contactoWeb = '';
$empresaNombre = '';
$cfg = dbRow("SELECT nombre_comercial, correo, celular, telefono, web FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
if ($cfg) {
  $dec = function ($t) { $t = (string)$t; for ($i = 0; $i < 3; $i++) { $d = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'); if ($d === $t) break; $t = $d; } return trim($t); };
  $empresaNombre = $dec($cfg['nombre_comercial']);
  $contactoEmail = $dec($cfg['correo']);
  $contactoCelular = $dec(!empty($cfg['celular']) ? $cfg['celular'] : $cfg['telefono']);
  $contactoWeb = $dec($cfg['web']);
}
$whatsapp = preg_replace('/\D+/', '', $contactoCelular);
if ($whatsapp !== '' && strlen($whatsapp) === 9) {
  $whatsapp = '51' . $whatsapp; // Peru por defecto
}
$mensajeWA = rawurlencode("Hola, quiero una demostración de " . $producto . " para mi negocio.");
$logueado = usuarioAutenticado();
// Marca de la empresa (Configuracion > Empresa y marca): logo, nombre y colores
$marca = marcaEmpresa();
$marcaLogo = marcaUrlLogo('');
$marcaNombre = $marca['nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($producto); ?> — Sistema de inventario, ventas y caja para tu negocio</title>
  <meta name="description" content="<?php echo e($producto); ?>: controla stock, compras, ventas, caja, cuentas por cobrar y utilidades desde un solo panel. Rápido, seguro y listo para tu tienda, ferretería, farmacia o minimarket.">
  <link rel="icon" href="<?php echo e($marcaLogo); ?>">
  <link rel="stylesheet" href="public/css/font-awesome.min.css">
  <link rel="stylesheet" href="public/css/landing.css?v=<?php echo e(APP_VERSION); ?>">
  <style>
    :root{
      --p: <?php echo e($marca['primario']); ?>;
      --p-dark: <?php echo e($marca['primario_oscuro']); ?>;
      --p-soft: <?php echo e($marca['primario_suave']); ?>;
      --a: <?php echo e($marca['secundario']); ?>;
      --a-dark: <?php echo e($marca['secundario_oscuro']); ?>;
    }
  </style>
</head>
<body>

<header class="site-nav" id="top">
  <div class="container nav-inner">
    <a href="#top" class="nav-brand">
      <img src="<?php echo e($marcaLogo); ?>" alt="<?php echo e($marcaNombre); ?>" onerror="this.src='public/img/brand-store.svg'">
      <span><?php echo e($marcaNombre); ?></span>
    </a>
    <nav class="nav-links" id="navLinks">
      <a href="#funciones">Funciones</a>
      <a href="#como-funciona">Cómo funciona</a>
      <a href="#seguridad">Seguridad</a>
      <a href="#planes">Planes</a>
      <a href="#faq">Preguntas</a>
    </nav>
    <div class="nav-cta">
      <?php if ($logueado) { ?>
        <a href="<?php echo e(paginaInicioUsuario('vistas/')); ?>" class="btn btn-primary"><i class="fa fa-dashboard"></i> Ir al panel</a>
      <?php } else { ?>
        <a href="vistas/login.php" class="btn btn-ghost"><i class="fa fa-sign-in"></i> Ingresar</a>
        <a href="#contacto" class="btn btn-primary">Solicitar demo</a>
      <?php } ?>
      <button class="nav-toggle" id="navToggle" aria-label="Menú"><i class="fa fa-bars"></i></button>
    </div>
  </div>
</header>

<main>
  <!-- HERO -->
  <section class="hero">
    <div class="container hero-grid">
      <div class="hero-copy reveal">
        <span class="eyebrow"><i class="fa fa-bolt"></i> Gestión comercial para PyMEs</span>
        <h1>Tu inventario, ventas y caja <span class="hl">bajo control</span>, sin complicaciones.</h1>
        <p class="lead"><?php echo e($producto); ?> centraliza artículos, compras, ventas, créditos, caja diaria y reportes de utilidad en un panel rápido y fácil de usar. Instálalo en tu propio servidor y sé dueño de tus datos.</p>
        <div class="hero-actions">
          <a href="#contacto" class="btn btn-primary btn-lg"><i class="fa fa-calendar-check-o"></i> Solicitar una demo</a>
          <a href="vistas/login.php" class="btn btn-outline btn-lg"><i class="fa fa-sign-in"></i> Ingresar al sistema</a>
        </div>
        <ul class="hero-checks">
          <li><i class="fa fa-check"></i> Sin mensualidades obligatorias</li>
          <li><i class="fa fa-check"></i> Funciona en PC, tablet y celular</li>
          <li><i class="fa fa-check"></i> Datos en tu servidor</li>
        </ul>
      </div>
      <div class="hero-visual reveal delay-1">
        <div class="mock-window">
          <div class="mock-bar"><span></span><span></span><span></span><em>escritorio · <?php echo e($producto); ?></em></div>
          <div class="mock-body">
            <aside class="mock-side">
              <div class="mock-logo"></div>
              <div class="mock-item active"></div>
              <div class="mock-item"></div>
              <div class="mock-item"></div>
              <div class="mock-item"></div>
              <div class="mock-item"></div>
            </aside>
            <div class="mock-main">
              <div class="mock-kpis">
                <div class="mock-kpi k1"><small>Ventas de hoy</small><strong>S/ 4,820</strong><i>+12%</i></div>
                <div class="mock-kpi k2"><small>Utilidad del mes</small><strong>S/ 18,340</strong><i>34% margen</i></div>
                <div class="mock-kpi k3"><small>Stock bajo mínimo</small><strong>7 ítems</strong><i>revisar</i></div>
              </div>
              <div class="mock-chart">
                <div class="bars">
                  <span style="--h:40%"></span><span style="--h:55%"></span><span style="--h:48%"></span><span style="--h:70%"></span><span style="--h:62%"></span><span style="--h:85%"></span><span style="--h:78%"></span><span style="--h:92%"></span>
                </div>
                <svg viewBox="0 0 320 90" preserveAspectRatio="none"><path d="M0,70 C40,60 60,30 100,40 S160,70 200,45 S260,10 320,20" fill="none" stroke="#f59e0b" stroke-width="3" stroke-linecap="round"/></svg>
              </div>
              <div class="mock-table">
                <div class="row head"><span>Producto</span><span>Stock</span><span>Vendido</span></div>
                <div class="row"><span>Perno hexagonal 1/2"</span><span class="pill ok">240</span><span>S/ 980</span></div>
                <div class="row"><span>Tuerca galvanizada</span><span class="pill low">12</span><span>S/ 640</span></div>
                <div class="row"><span>Arandela plana</span><span class="pill empty">0</span><span>S/ 310</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="floating-card fc1"><i class="fa fa-barcode"></i><div><strong>Lector de códigos</strong><small>Venta en 3 segundos</small></div></div>
        <div class="floating-card fc2"><i class="fa fa-bell"></i><div><strong>Alerta de stock</strong><small>7 artículos por reponer</small></div></div>
      </div>
    </div>
  </section>

  <!-- STATS -->
  <section class="stats">
    <div class="container stats-grid">
      <div class="stat reveal"><strong>9</strong><span>módulos integrados</span></div>
      <div class="stat reveal delay-1"><strong>3 seg</strong><span>para registrar una venta</span></div>
      <div class="stat reveal delay-2"><strong>100%</strong><span>tus datos, en tu servidor</span></div>
      <div class="stat reveal delay-3"><strong>0</strong><span>mensualidades obligatorias</span></div>
    </div>
  </section>

  <!-- FUNCIONES -->
  <section class="section" id="funciones">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Funciones</span>
        <h2>Todo lo que tu negocio necesita, en un solo lugar</h2>
        <p>Diseñado para tiendas, ferreterías, farmacias, minimarkets, distribuidoras y cualquier negocio que compre y venda productos.</p>
      </div>
      <div class="features-grid">
        <article class="feature reveal"><div class="f-ico c1"><i class="fa fa-cubes"></i></div><h3>Inventario inteligente</h3><p>Artículos con código de barras, categorías, unidades de medida, stock mínimo, precios de compra y venta, imágenes y kardex valorizado.</p></article>
        <article class="feature reveal delay-1"><div class="f-ico c2"><i class="fa fa-shopping-cart"></i></div><h3>Punto de venta rápido</h3><p>Escanea o busca, elige medio de pago (efectivo, tarjeta, Yape, Plin, transferencia), vende al contado o al crédito e imprime boleta, factura o ticket.</p></article>
        <article class="feature reveal delay-2"><div class="f-ico c3"><i class="fa fa-truck"></i></div><h3>Compras y proveedores</h3><p>Registra ingresos de mercadería, actualiza costos y precios automáticamente y controla cuentas por pagar con vencimientos.</p></article>
        <article class="feature reveal"><div class="f-ico c4"><i class="fa fa-money"></i></div><h3>Caja diaria</h3><p>Apertura, movimientos automáticos por cada venta y cobro, cierre con arqueo y diferencia, historial por usuario.</p></article>
        <article class="feature reveal delay-1"><div class="f-ico c5"><i class="fa fa-line-chart"></i></div><h3>Utilidad real</h3><p>Sabe cuánto ganas por producto, categoría, vendedor y periodo. Ranking de más y menos vendidos, ventas por hora y por medio de pago.</p></article>
        <article class="feature reveal delay-2"><div class="f-ico c6"><i class="fa fa-bell"></i></div><h3>Alertas y sugerencias</h3><p>Stock agotado o bajo mínimo, productos sin movimiento, cuentas vencidas y compras sugeridas según tu ritmo de ventas.</p></article>
        <article class="feature reveal"><div class="f-ico c7"><i class="fa fa-users"></i></div><h3>Usuarios y permisos</h3><p>Cada colaborador ve solo lo que le corresponde: ventas, almacén, compras, caja, reportes o administración. Auditoría de acciones.</p></article>
        <article class="feature reveal delay-1"><div class="f-ico c8"><i class="fa fa-file-pdf-o"></i></div><h3>Reportes y exportación</h3><p>Comprobantes en PDF y ticket térmico, exportación a Excel, CSV y PDF de cualquier listado con un clic.</p></article>
        <article class="feature reveal delay-2"><div class="f-ico c9"><i class="fa fa-paint-brush"></i></div><h3>Tu marca</h3><p>Logo, colores, series de comprobantes, impuesto y moneda configurables. Backups desde el panel.</p></article>
      </div>
    </div>
  </section>

  <!-- COMO FUNCIONA -->
  <section class="section alt" id="como-funciona">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Cómo funciona</span>
        <h2>Operativo en un día</h2>
      </div>
      <div class="steps">
        <div class="step reveal"><span class="n">1</span><h3>Instalamos y configuramos</h3><p>En tu servidor local o en la nube. Cargamos tu logo, series, impuesto, usuarios y permisos.</p></div>
        <div class="step reveal delay-1"><span class="n">2</span><h3>Cargas tu inventario</h3><p>Registra artículos con código de barras o impórtalos desde tu lista actual. Define stocks mínimos.</p></div>
        <div class="step reveal delay-2"><span class="n">3</span><h3>Empiezas a vender</h3><p>Ventas, compras, caja y cuentas se conectan solas. Tú solo miras el escritorio para decidir.</p></div>
      </div>
    </div>
  </section>

  <!-- SEGURIDAD -->
  <section class="section" id="seguridad">
    <div class="container split">
      <div class="reveal">
        <span class="eyebrow">Seguridad</span>
        <h2>Construido para proteger tu información</h2>
        <p class="lead-sm">Un sistema de gestión guarda tus ventas, tus costos y tus clientes. Por eso <?php echo e($producto); ?> aplica prácticas de seguridad modernas de serie.</p>
        <ul class="check-list">
          <li><i class="fa fa-shield"></i> Contraseñas cifradas con bcrypt y bloqueo ante intentos fallidos</li>
          <li><i class="fa fa-shield"></i> Consultas preparadas contra inyección SQL y protección CSRF</li>
          <li><i class="fa fa-shield"></i> Sesiones con expiración por inactividad y cookies protegidas</li>
          <li><i class="fa fa-shield"></i> Permisos por módulo y auditoría de cada acción</li>
          <li><i class="fa fa-shield"></i> Backups y restauración desde el panel, con copia previa automática</li>
        </ul>
      </div>
      <div class="security-card reveal delay-1">
        <div class="sec-row"><i class="fa fa-lock"></i><div><strong>Sesión protegida</strong><small>HttpOnly · SameSite · expiración automática</small></div><span class="ok">Activo</span></div>
        <div class="sec-row"><i class="fa fa-key"></i><div><strong>Contraseñas bcrypt</strong><small>Migración automática desde sistemas antiguos</small></div><span class="ok">Activo</span></div>
        <div class="sec-row"><i class="fa fa-user-secret"></i><div><strong>Anti fuerza bruta</strong><small>5 intentos · bloqueo de 15 minutos</small></div><span class="ok">Activo</span></div>
        <div class="sec-row"><i class="fa fa-history"></i><div><strong>Auditoría</strong><small>Quién, qué y cuándo, en cada módulo</small></div><span class="ok">Activo</span></div>
        <div class="sec-row"><i class="fa fa-database"></i><div><strong>Backups</strong><small>Descarga y restauración con un clic</small></div><span class="ok">Activo</span></div>
      </div>
    </div>
  </section>

  <!-- PLANES -->
  <section class="section alt" id="planes">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Planes</span>
        <h2>Elige cómo quieres empezar</h2>
        <p>Licencia por instalación. Sin límite de artículos, ventas ni usuarios.</p>
      </div>
      <div class="plans">
        <div class="plan reveal">
          <h3>Local</h3>
          <p class="plan-desc">Para una tienda con una o varias PC en red.</p>
          <div class="price">Licencia única</div>
          <ul>
            <li><i class="fa fa-check"></i> Todos los módulos</li>
            <li><i class="fa fa-check"></i> Instalación en tu PC/servidor</li>
            <li><i class="fa fa-check"></i> Configuración inicial y capacitación</li>
            <li><i class="fa fa-check"></i> Soporte 30 días</li>
          </ul>
          <a href="#contacto" class="btn btn-outline">Cotizar</a>
        </div>
        <div class="plan featured reveal delay-1">
          <span class="badge">Más elegido</span>
          <h3>Nube</h3>
          <p class="plan-desc">Accede desde cualquier lugar y dispositivo.</p>
          <div class="price">Licencia + hosting</div>
          <ul>
            <li><i class="fa fa-check"></i> Todo lo del plan Local</li>
            <li><i class="fa fa-check"></i> Servidor con HTTPS y dominio</li>
            <li><i class="fa fa-check"></i> Backups automáticos diarios</li>
            <li><i class="fa fa-check"></i> Soporte y actualizaciones continuas</li>
          </ul>
          <a href="#contacto" class="btn btn-primary">Solicitar demo</a>
        </div>
        <div class="plan reveal delay-2">
          <h3>A medida</h3>
          <p class="plan-desc">Para varias sucursales o integraciones.</p>
          <div class="price">Cotización</div>
          <ul>
            <li><i class="fa fa-check"></i> Múltiples almacenes o sucursales</li>
            <li><i class="fa fa-check"></i> Reportes y módulos personalizados</li>
            <li><i class="fa fa-check"></i> Integraciones (balanzas, e-commerce)</li>
            <li><i class="fa fa-check"></i> Acompañamiento dedicado</li>
          </ul>
          <a href="#contacto" class="btn btn-outline">Conversemos</a>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="section" id="faq">
    <div class="container narrow">
      <div class="section-head reveal">
        <span class="eyebrow">Preguntas frecuentes</span>
        <h2>Lo que suelen preguntarnos</h2>
      </div>
      <div class="faq reveal">
        <details open><summary>¿Necesito internet para usarlo?</summary><p>No. En el plan Local funciona en tu red interna sin conexión. En el plan Nube accedes desde cualquier lugar con internet.</p></details>
        <details><summary>¿Puedo usar mi lector de código de barras?</summary><p>Sí. Cualquier lector USB o Bluetooth que funcione como teclado sirve. El punto de venta agrega el producto automáticamente al escanear.</p></details>
        <details><summary>¿Emite facturación electrónica?</summary><p>El sistema imprime boletas, facturas y tickets internos en PDF y formato térmico. La integración con facturación electrónica se cotiza por separado según el país.</p></details>
        <details><summary>¿Qué pasa con mis datos si dejo de usarlo?</summary><p>Son tuyos. Puedes descargar un backup completo de la base de datos desde el panel en cualquier momento.</p></details>
        <details><summary>¿Cuántos usuarios puedo crear?</summary><p>Los que necesites. Cada uno con permisos específicos por módulo (ventas, almacén, compras, caja, reportes, administración).</p></details>
      </div>
    </div>
  </section>

  <!-- CONTACTO -->
  <section class="cta" id="contacto">
    <div class="container cta-inner reveal">
      <div>
        <h2>¿Listo para ordenar tu negocio?</h2>
        <p>Agenda una demostración de 20 minutos. Te mostramos el sistema con tus propios productos.</p>
      </div>
      <div class="cta-actions">
        <?php if ($whatsapp !== '') { ?>
          <a class="btn btn-wa btn-lg" href="https://wa.me/<?php echo e($whatsapp); ?>?text=<?php echo $mensajeWA; ?>" target="_blank" rel="noopener"><i class="fa fa-whatsapp"></i> Escríbenos por WhatsApp</a>
        <?php } ?>
        <?php if ($contactoEmail !== '') { ?>
          <a class="btn btn-light btn-lg" href="mailto:<?php echo e($contactoEmail); ?>?subject=<?php echo rawurlencode('Demo de ' . $producto); ?>"><i class="fa fa-envelope-o"></i> <?php echo e($contactoEmail); ?></a>
        <?php } ?>
        <?php if ($whatsapp === '' && $contactoEmail === '') { ?>
          <a class="btn btn-light btn-lg" href="vistas/login.php"><i class="fa fa-sign-in"></i> Ingresar al sistema</a>
        <?php } ?>
      </div>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <img src="<?php echo e($marcaLogo); ?>" alt="" onerror="this.src='public/img/brand-store.svg'">
      <div>
        <strong><?php echo e($marcaNombre); ?></strong>
        <small><?php echo e($producto); ?> · sistema de gestión comercial v<?php echo e(APP_VERSION); ?></small>
      </div>
    </div>
    <nav class="footer-links">
      <a href="#funciones">Funciones</a>
      <a href="#planes">Planes</a>
      <a href="#faq">Preguntas</a>
      <a href="vistas/login.php">Ingresar</a>
    </nav>
    <div class="footer-copy">&copy; <?php echo date('Y'); ?> <?php echo e($empresaNombre !== '' ? $empresaNombre : $producto); ?>. Todos los derechos reservados.</div>
  </div>
</footer>

<script>
(function () {
  var toggle = document.getElementById('navToggle');
  var links = document.getElementById('navLinks');
  if (toggle && links) {
    toggle.addEventListener('click', function () { links.classList.toggle('open'); });
    links.addEventListener('click', function (e) { if (e.target.tagName === 'A') links.classList.remove('open'); });
  }
  var nav = document.querySelector('.site-nav');
  var onScroll = function () { if (nav) nav.classList.toggle('scrolled', window.scrollY > 10); };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in'); });
  }
})();
</script>
</body>
</html>
