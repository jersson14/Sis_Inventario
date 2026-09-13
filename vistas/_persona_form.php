<?php
// Parcial compartido por cliente.php y proveedor.php. Requiere $personaTipo.
$esCliente = ($personaTipo === 'Cliente');
$plural = $esCliente ? 'Clientes' : 'Proveedores';
$singular = $esCliente ? 'cliente' : 'proveedor';
$icono = $esCliente ? 'fa-users' : 'fa-truck';
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> <?php echo $esCliente ? 'Ventas' : 'Compras'; ?> <i class="fa fa-chevron-right"></i> <?php echo $plural; ?></div>
        <h1><span class="page-icon"><i class="fa <?php echo $icono; ?>"></i></span> <?php echo $plural; ?></h1>
        <p><?php echo $esCliente ? 'Directorio de clientes con historial de compras y créditos.' : 'Directorio de proveedores para tus ingresos de mercadería.'; ?></p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-plus"></i> Nuevo <?php echo $singular; ?></button>
      </div>
    </div>

    <div class="box" id="listadoregistros">
      <div class="box-body table-responsive">
        <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
          <thead><tr><th>Opciones</th><th>Nombre</th><th>Tipo doc.</th><th>N° documento</th><th>Teléfono</th><th>Email</th><th>Estado</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="box" id="formularioregistros">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-pencil"></i> <span id="formTitulo">Nuevo <?php echo $singular; ?></span></h3>
        <div class="box-tools"><button class="btn btn-default btn-sm" onclick="cancelarform()" type="button"><i class="fa fa-arrow-left"></i> Volver al listado</button></div>
      </div>
      <div class="box-body">
        <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
          <input type="hidden" name="idpersona" id="idpersona">
          <input type="hidden" name="tipo_persona" id="tipo_persona" value="<?php echo e($personaTipo); ?>">
          <div class="form-group col-lg-6 col-md-6 col-xs-12">
            <label for="nombre">Nombre o razón social <span class="req">*</span></label>
            <input class="form-control" type="text" name="nombre" id="nombre" maxlength="100" placeholder="Nombre del <?php echo $singular; ?>" required>
          </div>
          <div class="form-group col-lg-3 col-md-3 col-xs-12">
            <label for="tipo_documento">Tipo de documento</label>
            <select class="form-control" name="tipo_documento" id="tipo_documento">
              <option value="DNI">DNI</option>
              <option value="RUC">RUC</option>
              <option value="CEDULA">Cédula</option>
              <option value="PASAPORTE">Pasaporte</option>
              <option value="OTRO">Otro</option>
            </select>
          </div>
          <div class="form-group col-lg-3 col-md-3 col-xs-12">
            <label for="num_documento">N° de documento</label>
            <input class="form-control" type="text" name="num_documento" id="num_documento" maxlength="20" placeholder="Ej. 20123456789">
          </div>
          <div class="form-group col-lg-6 col-md-6 col-xs-12">
            <label for="direccion">Dirección</label>
            <input class="form-control" type="text" name="direccion" id="direccion" maxlength="70" placeholder="Dirección">
          </div>
          <div class="form-group col-lg-3 col-md-3 col-xs-12">
            <label for="telefono">Teléfono / WhatsApp</label>
            <input class="form-control" type="text" name="telefono" id="telefono" maxlength="20" placeholder="999 999 999">
          </div>
          <div class="form-group col-lg-3 col-md-3 col-xs-12">
            <label for="email">Email</label>
            <input class="form-control" type="email" name="email" id="email" maxlength="50" placeholder="correo@dominio.com">
          </div>
          <div class="form-group col-xs-12 form-actions-row">
            <button class="btn btn-primary" type="submit" id="btnGuardar"><i class="fa fa-save"></i> Guardar</button>
            <button class="btn btn-default" onclick="cancelarform()" type="button"><i class="fa fa-times"></i> Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>
