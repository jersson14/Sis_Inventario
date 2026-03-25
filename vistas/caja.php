<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';
if ($_SESSION['compras']==1 || $_SESSION['ventas']==1) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h1 class="box-title">Caja Diaria</h1>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-12">
                <div class="well well-sm" id="cajaEstado"></div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <h4>Abrir caja</h4>
                <form id="formAbrirCaja" class="row">
                  <div class="form-group col-md-4"><input type="number" step="0.01" min="0" class="form-control" name="monto_apertura" id="monto_apertura" placeholder="Monto apertura" required></div>
                  <div class="form-group col-md-6"><input type="text" class="form-control" name="observacion" id="obs_apertura" placeholder="Observacion"></div>
                  <div class="form-group col-md-2"><button class="btn btn-success form-control" type="submit"><i class="fa fa-unlock"></i></button></div>
                </form>
              </div>

              <div class="col-md-6">
                <h4>Movimiento rapido</h4>
                <form id="formMovimientoCaja" class="row">
                  <div class="form-group col-md-3">
                    <select class="form-control" name="tipo" id="mov_tipo">
                      <option value="INGRESO">INGRESO</option>
                      <option value="EGRESO">EGRESO</option>
                    </select>
                  </div>
                  <div class="form-group col-md-5"><input type="text" class="form-control" name="concepto" id="mov_concepto" placeholder="Concepto" required></div>
                  <div class="form-group col-md-3"><input type="number" step="0.01" min="0.01" class="form-control" name="monto" id="mov_monto" placeholder="Monto" required></div>
                  <div class="form-group col-md-1"><button class="btn btn-primary form-control" type="submit"><i class="fa fa-plus"></i></button></div>
                </form>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <h4>Cierre de caja</h4>
                <form id="formCerrarCaja" class="row">
                  <div class="form-group col-md-3"><input type="number" step="0.01" min="0" class="form-control" name="monto_cierre_real" id="monto_cierre_real" placeholder="Monto cierre real" required></div>
                  <div class="form-group col-md-7"><input type="text" class="form-control" name="observacion" id="obs_cierre" placeholder="Observacion de cierre"></div>
                  <div class="form-group col-md-2"><button class="btn btn-danger form-control" type="submit"><i class="fa fa-lock"></i> Cerrar</button></div>
                </form>
              </div>
            </div>

            <hr>
            <h4>Movimientos de caja abierta</h4>
            <table id="tblmovcaja" class="table table-striped table-bordered table-condensed table-hover">
              <thead><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Monto</th><th>Usuario</th></thead>
              <tbody></tbody>
            </table>

            <hr>
            <h4>Historial de cajas</h4>
            <table id="tblhistcaja" class="table table-striped table-bordered table-condensed table-hover">
              <thead><th>ID</th><th>Apertura</th><th>Cierre</th><th>Apertura S/</th><th>Ingresos S/</th><th>Egresos S/</th><th>Sistema S/</th><th>Real S/</th><th>Diferencia</th><th>Estado</th></thead>
              <tbody></tbody>
            </table>
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
<script src="scripts/caja.js"></script>
<?php
}
ob_end_flush();
?>
