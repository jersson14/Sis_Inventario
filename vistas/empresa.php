<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';
if ($_SESSION['acceso']==1) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h1 class="box-title">Configuracion de Empresa</h1>
          </div>
          <div class="panel-body">
            <form id="empresaForm" method="POST" enctype="multipart/form-data">
              <div class="row">
                <div class="form-group col-lg-4 col-md-4 col-sm-6 col-xs-12">
                  <label>Nombre comercial(*)</label>
                  <input type="text" class="form-control" name="nombre_comercial" id="nombre_comercial" required>
                </div>
                <div class="form-group col-lg-4 col-md-4 col-sm-6 col-xs-12">
                  <label>Razon social</label>
                  <input type="text" class="form-control" name="razon_social" id="razon_social">
                </div>
                <div class="form-group col-lg-4 col-md-4 col-sm-6 col-xs-12">
                  <label>RUC</label>
                  <input type="text" class="form-control" name="ruc" id="ruc" maxlength="20">
                </div>

                <div class="form-group col-lg-6 col-md-6 col-sm-12 col-xs-12">
                  <label>Direccion</label>
                  <input type="text" class="form-control" name="direccion" id="direccion" maxlength="180">
                </div>
                <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                  <label>Telefono</label>
                  <input type="text" class="form-control" name="telefono" id="telefono" maxlength="30">
                </div>
                <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                  <label>Celular</label>
                  <input type="text" class="form-control" name="celular" id="celular" maxlength="30">
                </div>

                <div class="form-group col-lg-4 col-md-4 col-sm-6 col-xs-12">
                  <label>Correo</label>
                  <input type="email" class="form-control" name="correo" id="correo" maxlength="120">
                </div>
                <div class="form-group col-lg-4 col-md-4 col-sm-6 col-xs-12">
                  <label>Web</label>
                  <input type="text" class="form-control" name="web" id="web" maxlength="120" placeholder="https://...">
                </div>
                <div class="form-group col-lg-4 col-md-4 col-sm-6 col-xs-12">
                  <label>Moneda</label>
                  <select class="form-control" name="moneda" id="moneda">
                    <option value="PEN">PEN - Soles</option>
                    <option value="USD">USD - Dolar</option>
                  </select>
                </div>

                <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                  <label>Color primario</label>
                  <input type="color" class="form-control" name="color_primario" id="color_primario" value="#0f766e">
                </div>
                <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                  <label>Color secundario</label>
                  <input type="color" class="form-control" name="color_secundario" id="color_secundario" value="#f59e0b">
                </div>
                <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                  <label>Serie boleta</label>
                  <input type="text" class="form-control" name="serie_boleta" id="serie_boleta" maxlength="10" value="B001">
                </div>
                <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                  <label>Serie factura</label>
                  <input type="text" class="form-control" name="serie_factura" id="serie_factura" maxlength="10" value="F001">
                </div>

                <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                  <label>Serie ticket</label>
                  <input type="text" class="form-control" name="serie_ticket" id="serie_ticket" maxlength="10" value="T001">
                </div>
                <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                  <label>Impuesto por defecto (%)</label>
                  <input type="number" step="0.01" min="0" class="form-control" name="impuesto_default" id="impuesto_default" value="18.00">
                </div>
                <div class="form-group col-lg-6 col-md-6 col-sm-12 col-xs-12">
                  <label>Logo</label>
                  <input type="file" class="form-control" name="logo" id="logo" accept="image/*">
                  <input type="hidden" name="logoactual" id="logoactual">
                </div>
                <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
                  <img id="logomuestra" src="../vistas/logo1.jpeg" alt="logo" style="max-height:90px;border:1px solid #e2e8f0;border-radius:10px;padding:4px;background:#fff;">
                </div>
              </div>

              <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Guardar configuracion</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php
}else{
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/empresa.js"></script>
<?php
}
ob_end_flush();
?>
