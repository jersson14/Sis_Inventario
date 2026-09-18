/* Toma de inventario: conteo fisico con lector de barras */
var URL_CONTEO = "../ajax/conteo.php?op=";
var conteo = null;          // conteo abierto
var lineas = [];            // lo contado (la ultima lectura primero)
var puedeAplicar = false;
var cola = [];              // lecturas en espera: se procesan de a una
var procesando = false;
var pausado = false;        // mientras se elige talla/lote o se busca por nombre
var loteRecordado = {};     // idarticulo -> eleccion de lote para las proximas lecturas
var eleccion = null;        // callback de la ventana "Elegir"
var tablaConteos = null;
var ultimoIdResaltado = 0;

function money(v){ return window.appMoney(v, 2); }
function cant(v){ return window.appCantidad(v); }
function esc(t){ return appEscapeHtml(t == null ? "" : String(t)); }
function fecha(iso){ return iso ? String(iso).substr(0, 10).split("-").reverse().join("/") : ""; }
function hora(iso){ return iso ? String(iso).substr(11, 5) : ""; }

function signo(v, texto){
	var n = Math.round((parseFloat(v) || 0) * 1000) / 1000;
	if (n === 0) { return '<span class="text-soft">0</span>'; }
	return '<span class="' + (n > 0 ? "text-success" : "text-danger") + '"><strong>' + (n > 0 ? "+" : "−") + esc(texto !== undefined ? texto : cant(Math.abs(n))) + "</strong></span>";
}

function detalleLinea(l){
	if (l.variante) { return esc(l.variante); }
	if (l.tipo_lote === "lote" || l.tipo_lote === "nuevo") {
		var t = l.lote_codigo ? "Lote " + esc(l.lote_codigo) : "Lote sin código";
		if (l.lote_vencimiento) { t += ' <small class="text-soft">vence ' + fecha(l.lote_vencimiento) + "</small>"; }
		if (l.tipo_lote === "nuevo") { t += ' <span class="label label-info">nuevo</span>'; }
		return t;
	}
	return l.tipo_lote === "sin_lote" ? '<span class="text-soft">Sin lote</span>' : "";
}

// ---------------------------------------------------------------------
// Estado general
// ---------------------------------------------------------------------

function cargarEstado(){
	$.get(URL_CONTEO + "estado", function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		puedeAplicar = !!r.puede_aplicar;
		conteo = r.conteo;
		if (!conteo) {
			$("#panelConteo").hide();
			$("#panelInicio").show();
			return;
		}
		$("#panelInicio").hide();
		$("#panelConteo").show();
		$("#cNombre").text(conteo.nombre);
		$("#cDatos").html(
			(conteo.almacen ? '<i class="fa fa-building-o"></i> ' + esc(conteo.almacen) + ' · ' : '') +
			'<i class="fa fa-map-marker"></i> ' + esc(conteo.categoria || "Todo el almacén") +
			(conteo.por_lote ? ' · <i class="fa fa-tags"></i> por lote' : "") +
			' · <i class="fa fa-user"></i> inició ' + esc(conteo.usuario) + " el " + esc(conteo.fecha_inicio) +
			(conteo.observacion ? " · " + esc(conteo.observacion) : ""));
		$("#btnAnular").toggle(puedeAplicar);
		pintarResumen(r.resumen);
		cargarLineas();
		setTimeout(function(){ $("#cScan").focus(); }, 100);
	});
}

function cargarLineas(){
	if (!conteo) { return; }
	$.get(URL_CONTEO + "lineas", { idconteo: conteo.idconteo }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		if (r.conteo.estado !== "ABIERTO") { conteoCerradoEnOtraPc(r.conteo.estado); return; }
		lineas = r.lineas || [];
		pintarLineas();
		pintarResumen(r.resumen);
	});
}

var resumenDiferido = appDebounce(function(){
	if (!conteo) { return; }
	$.get(URL_CONTEO + "estado", function(resp){
		var r = appParseJson(resp, null);
		if (r && r.ok && r.resumen) { pintarResumen(r.resumen); }
	});
}, 1200);

function pintarResumen(r){
	if (!r) { return; }
	$("#rContados").text(r.articulos_contados + " / " + r.articulos_con_stock);
	$("#rContadosTxt").text("Artículos contados · " + cant(r.unidades) + " unidades");
	$("#rSobrante").text(money(r.valor_sobrante));
	$("#rFaltante").text(money(r.valor_faltante));
	$("#rPendientes").text(r.pendientes);
	$("#rPendientesTxt").text("Pendientes por contar · " + money(r.valor_pendientes));
	$("#badgePendientes").text(r.pendientes);
}

function conteoCerradoEnOtraPc(estado){
	appNotify("info", "Este conteo ya fue " + String(estado || "cerrado").toLowerCase() + " desde otra sesión.", 6000);
	conteo = null;
	lineas = [];
	cola = [];
	cargarEstado();
	if (tablaConteos) { tablaConteos.ajax.reload(null, false); }
}

// ---------------------------------------------------------------------
// Tabla de lo contado
// ---------------------------------------------------------------------

function pintarLineas(){
	var q = $.trim($("#fLineas").val()).toLowerCase();
	var f = $("#fDif").val();
	var html = "";
	var n = 0;
	lineas.forEach(function(l){
		if (f === "dif" && l.diferencia === 0) { return; }
		if (f === "falta" && !(l.diferencia < 0)) { return; }
		if (f === "sobra" && !(l.diferencia > 0)) { return; }
		if (q && (l.nombre + " " + l.codigo + " " + (l.lote_codigo || "") + " " + (l.variante || "")).toLowerCase().indexOf(q) === -1) { return; }
		n++;
		var fraccion = String(l.permite_fraccion) === "1" && window.appNegocioTiene("fracciones");
		html += '<tr data-id="' + l.iddetalle + '"' + (l.iddetalle === ultimoIdResaltado ? ' class="conteo-resaltado"' : "") + ">" +
			"<td><strong>" + esc(l.nombre) + '</strong><br><small class="text-soft">' + esc(l.codigo) + "</small></td>" +
			"<td>" + detalleLinea(l) + "</td>" +
			'<td class="text-right"><div class="input-group input-group-sm conteo-editar"><input type="number" class="form-control text-right conteo-input" min="0" step="' + (fraccion ? "0.001" : "1") + '" value="' + l.cantidad + '" data-id="' + l.iddetalle + '" title="Corrige lo contado y presiona Enter"><span class="input-group-addon">' + esc(l.unidad) + "</span></div></td>" +
			'<td class="text-right">' + cant(l.stock_sistema) + "</td>" +
			'<td class="text-right">' + signo(l.diferencia) + "</td>" +
			'<td class="text-right">' + (l.valor === 0 ? '<span class="text-soft">—</span>' : signo(l.valor, money(Math.abs(l.valor)))) + "</td>" +
			'<td><small>' + esc(l.usuario) + '<br><span class="text-soft">' + hora(l.actualizado) + " · " + l.lecturas + " lect.</span></small></td>" +
			'<td><button type="button" class="btn btn-default btn-xs btn-quitar" data-id="' + l.iddetalle + '" title="Quitar esta línea del conteo"><i class="fa fa-trash"></i></button></td>' +
			"</tr>";
	});
	if (!html) {
		html = '<tr><td colspan="8" class="text-center text-soft" style="padding:24px">' + (lineas.length ? "Nada coincide con el filtro." : "Todavía no hay nada contado. Escanea el primer producto.") + "</td></tr>";
	}
	$("#tblLineas tbody").html(html);
	$("#lineasTotal").text(n + " de " + lineas.length + " línea(s)");
}

function upsertLinea(linea){
	var i = lineas.findIndex(function(l){ return l.iddetalle === linea.iddetalle; });
	if (i >= 0) { lineas.splice(i, 1); }
	lineas.unshift(linea);
	ultimoIdResaltado = linea.iddetalle;
	pintarLineas();
}

// ---------------------------------------------------------------------
// Lecturas
// ---------------------------------------------------------------------

// "12*CODIGO" cuenta 12 de una vez; si no, la cantidad del campo (y vuelve a 1)
function leer(texto){
	texto = $.trim(texto || "");
	$("#cScan").val("");
	if (!texto || !conteo) { return; }
	var cantidad = parseFloat(String($("#cCantidad").val()).replace(",", ".")) || 1;
	// Solo "*": una "x" confundiria codigos reales como "2X4"
	var m = /^(\d+(?:[.,]\d+)?)\s*\*\s*(.+)$/.exec(texto);
	if (m) { cantidad = parseFloat(m[1].replace(",", ".")); texto = $.trim(m[2]); }
	$("#cCantidad").val(1);
	if (cantidad <= 0) { appNotify("warning", "La cantidad debe ser mayor que cero."); return; }
	cola.push({ codigo: texto, cantidad: cantidad });
	procesarCola();
}

function procesarCola(){
	if (procesando || pausado || !cola.length) { return; }
	procesando = true;
	var item = cola.shift();
	$.post(URL_CONTEO + "codigo", { idconteo: conteo.idconteo, codigo: item.codigo }, function(resp){
		var r = appParseJson(resp, { ok: false, message: resp });
		if (r.cerrado) { procesando = false; conteoCerradoEnOtraPc(); return; }
		if (!r.ok) {
			window.appSonido("error");
			mostrarError(r.message || "Código no reconocido");
			procesando = false;
			if (r.no_encontrado) { abrirBuscar(item, r.message, r.sugerencias || []); return; }
			procesarCola();
			return;
		}
		decidir(r.ficha, r.lectura || {}, item.cantidad, "sumar", function(){ procesando = false; procesarCola(); });
	}).fail(function(){
		window.appSonido("error");
		mostrarError("Sin conexión con el servidor: vuelve a escanear " + item.codigo);
		procesando = false;
		procesarCola();
	});
}

/**
 * Con la ficha del articulo decide que falta: talla/color, lote o nada.
 * cantidad en la unidad leida (una caja multiplica por su factor).
 */
function decidir(ficha, lectura, cantidad, modo, listo){
	var factor = parseFloat(lectura.factor) || 1;
	var total = Math.round(cantidad * factor * 1000) / 1000;
	var base = { idarticulo: ficha.idarticulo, cantidad: total, modo: modo };
	var extra = lectura.presentacion ? lectura.presentacion + " (" + cant(factor) + " " + ficha.unidad + ")" : "";
	if (ficha.variantes.length) {
		if (lectura.idvariante) { registrar($.extend(base, { idvariante: lectura.idvariante }), extra, listo); return; }
		elegirVariante(ficha, total, function(idvariante){
			if (!idvariante) { cancelada(listo); return; }
			registrar($.extend(base, { idvariante: idvariante }), extra, listo);
		});
		return;
	}
	if (ficha.por_lote) {
		if (lectura.idlote) { registrar($.extend(base, { idlote: lectura.idlote }), extra, listo); return; }
		var rec = loteRecordado[ficha.idarticulo];
		if (rec) { registrar($.extend(base, rec.datos), extra, listo); return; }
		if (!ficha.lotes.length && !ficha.lotes_nuevos.length) { registrar(base, extra, listo); return; }
		elegirLote(ficha, total, function(datos, texto, recordar){
			if (!datos) { cancelada(listo); return; }
			if (recordar) { loteRecordado[ficha.idarticulo] = { datos: datos, texto: texto, nombre: ficha.nombre }; pintarRecordados(); }
			registrar($.extend(base, datos), extra, listo);
		});
		return;
	}
	registrar(base, extra, listo);
}

function cancelada(listo){
	mostrarError("Lectura cancelada: no se contó.");
	if (listo) { listo(); }
}

function registrar(datos, extra, listo){
	datos.idconteo = conteo.idconteo;
	$.post(URL_CONTEO + "registrar", datos, function(resp){
		var r = appParseJson(resp, { ok: false, message: resp });
		if (r.cerrado) { conteoCerradoEnOtraPc(); return; }
		if (!r.ok) {
			window.appSonido("error");
			mostrarError(r.message || "No se pudo registrar.");
		} else {
			window.appSonido("ok");
			upsertLinea(r.linea);
			mostrarUltima(r.linea, datos.modo === "fijar" ? null : datos.cantidad, extra);
			resumenDiferido();
		}
		if (listo) { listo(); }
		if (!pausado) { $("#cScan").focus(); }
	}).fail(function(){
		window.appSonido("error");
		mostrarError("Sin conexión con el servidor: la lectura no se guardó.");
		if (listo) { listo(); }
	});
}

function mostrarUltima(l, sumado, extra){
	var det = detalleLinea(l);
	$("#ultimaLectura").removeClass("vacia is-error").html(
		'<div class="conteo-ultima-cant">' + (sumado === null ? '<i class="fa fa-pencil"></i>' : "+" + cant(sumado)) + "</div>" +
		'<div class="conteo-ultima-info"><strong>' + esc(l.nombre) + "</strong>" + (det ? " · " + det : "") + (extra ? ' <span class="label label-default">' + esc(extra) + "</span>" : "") +
		'<div class="conteo-ultima-num">Contado <strong>' + cant(l.cantidad) + " " + esc(l.unidad) + "</strong> · Sistema " + cant(l.stock_sistema) + " · Diferencia " + signo(l.diferencia) + "</div></div>");
}

function mostrarError(msg){
	$("#ultimaLectura").removeClass("vacia").addClass("is-error").html('<i class="fa fa-exclamation-triangle"></i> ' + esc(msg));
}

function pintarRecordados(){
	var ids = Object.keys(loteRecordado);
	if (!ids.length) { $("#loteRecordado").hide().empty(); return; }
	$("#loteRecordado").show().html('<i class="fa fa-thumb-tack"></i> Lotes fijados: ' + ids.map(function(id){
		var r = loteRecordado[id];
		return '<span class="label label-info">' + esc(r.nombre) + ": " + esc(r.texto) + ' <a href="#" class="olvidar-lote" data-id="' + id + '" title="Volver a preguntar el lote">&times;</a></span>';
	}).join(" "));
}

// ---------------------------------------------------------------------
// Elegir talla/color o lote
// ---------------------------------------------------------------------

function abrirEleccion(titulo, ayuda, opciones, callback, conLoteNuevo, conRecordar){
	pausado = true;
	eleccion = callback;
	$("#elegirTitulo").html(titulo);
	$("#elegirAyuda").text(ayuda);
	var html = "";
	opciones.forEach(function(o, i){
		html += '<button type="button" class="btn btn-default btn-block conteo-opcion" data-i="' + i + '">' +
			(i < 9 ? '<span class="conteo-tecla">' + (i + 1) + "</span>" : "") + o.html + "</button>";
	});
	$("#elegirOpciones").html(html).data("opciones", opciones);
	$("#elegirLoteNuevo").toggle(!!conLoteNuevo);
	$("#lnCodigo, #lnVence").val("");
	$("#elegirRecordarGrupo").toggle(!!conRecordar);
	$("#modalElegir").modal("show");
}

function cerrarEleccion(valor, texto){
	var cb = eleccion;
	eleccion = null;
	$("#modalElegir").modal("hide");
	if (cb) { cb(valor, texto, $("#elegirRecordar").is(":checked")); }
}

function elegirVariante(ficha, total, callback){
	var ops = ficha.variantes.map(function(v){
		return { valor: v.idvariante, texto: v.etiqueta, html: "<strong>" + esc(v.etiqueta) + "</strong>" + (v.codigo ? ' <small class="text-soft">' + esc(v.codigo) + "</small>" : "") +
			(v.contado !== null ? ' <span class="conteo-ya text-soft">contado ' + cant(v.contado) + "</span>" : "") };
	});
	abrirEleccion('<i class="fa fa-tag"></i> ' + esc(ficha.nombre) + " · +" + cant(total), "¿Qué talla / color es? Toca una opción o presiona su número.", ops, callback, false, false);
}

function elegirLote(ficha, total, callback){
	var ops = ficha.lotes.map(function(l){
		var estado = l.dias === null ? "" : (l.dias < 0 ? ' <span class="label label-danger">vencido</span>' : (l.dias <= 30 ? ' <span class="label label-warning">' + l.dias + " días</span>" : ""));
		var texto = (l.codigo_lote || "Lote #" + l.idlote) + (l.fecha_vencimiento ? " · vence " + fecha(l.fecha_vencimiento) : "");
		return { valor: { idlote: l.idlote }, texto: texto, html: "<strong>" + esc(texto) + "</strong>" + estado + (l.contado !== null ? ' <span class="conteo-ya text-soft">contado ' + cant(l.contado) + "</span>" : "") };
	});
	ficha.lotes_nuevos.forEach(function(l){
		var texto = (l.codigo_lote || "Lote sin código") + (l.fecha_vencimiento ? " · vence " + fecha(l.fecha_vencimiento) : "");
		ops.push({ valor: { lote_codigo: l.codigo_lote || "", lote_vencimiento: l.fecha_vencimiento || "" }, texto: texto,
			html: "<strong>" + esc(texto) + '</strong> <span class="label label-info">nuevo</span> <span class="conteo-ya text-soft">contado ' + cant(l.contado) + "</span>" });
	});
	ops.push({ valor: {}, texto: "Sin lote", html: '<span class="text-soft">Sin lote (no se sabe el lote)</span>' + (ficha.contado_sin_lote !== null ? ' <span class="conteo-ya text-soft">contado ' + cant(ficha.contado_sin_lote) + "</span>" : "") });
	abrirEleccion('<i class="fa fa-tags"></i> ' + esc(ficha.nombre) + " · +" + cant(total), "¿De qué lote es? Si encontraste un lote que el sistema no tiene, regístralo abajo.", ops, callback, true, true);
}

// ---------------------------------------------------------------------
// Buscar por nombre (codigo no reconocido)
// ---------------------------------------------------------------------

var pendienteBuscar = null;

function abrirBuscar(item, aviso, sugerencias){
	pausado = true;
	pendienteBuscar = item;
	$("#buscarAviso").text(aviso || "");
	$("#buscarTexto").val(/^\d+$/.test(item.codigo) ? "" : item.codigo);
	pintarSugerencias(sugerencias);
	$("#modalBuscar").modal("show");
}

function pintarSugerencias(items){
	var html = items.map(function(a){
		return '<a href="#" class="list-group-item conteo-sugerencia" data-id="' + a.idarticulo + '"><strong>' + esc(a.nombre) + '</strong> <small class="text-soft">' + esc(a.codigo) + '</small><span class="pull-right text-soft">stock ' + cant(a.stock) + " " + esc(a.unidad) + "</span></a>";
	}).join("");
	$("#buscarResultados").html(html || '<div class="text-soft" style="padding:8px">Escribe al menos 2 letras del nombre.</div>');
}

var buscarDiferido = appDebounce(function(){
	var q = $.trim($("#buscarTexto").val());
	if (q.length < 2 || !conteo) { pintarSugerencias([]); return; }
	$.get(URL_CONTEO + "buscar", { idconteo: conteo.idconteo, q: q }, function(resp){
		var r = appParseJson(resp, null);
		pintarSugerencias(r && r.items ? r.items : []);
	});
}, 250);

// soloSimple: "No hay" solo aplica a productos sin tallas ni lotes (con tallas o
// lotes cada uno se cuenta aparte, o se usa "poner en cero lo no contado")
function contarArticulo(idarticulo, cantidad, modo, soloSimple, alRechazar){
	$.get(URL_CONTEO + "ficha", { idconteo: conteo.idconteo, idarticulo: idarticulo }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { appNotify("error", (r && r.message) || "No se pudo cargar el artículo."); return; }
		var f = r.ficha;
		if (soloSimple && (f.variantes.length || (f.por_lote && (f.lotes.length || f.lotes_nuevos.length)))) {
			appNotify("warning", f.nombre + " tiene " + (f.variantes.length ? "tallas/colores" : "lotes") + ": usa Contar, o al aplicar marca \"poner en cero lo que no se contó\".", 7000);
			if (alRechazar) { alRechazar(); }
			return;
		}
		decidir(f, {}, cantidad, modo, null);
	});
}

// ---------------------------------------------------------------------
// Pendientes, aplicar, anular
// ---------------------------------------------------------------------

function abrirPendientes(){
	$("#tblPendientes tbody").html('<tr><td colspan="5" class="text-center text-soft">Cargando…</td></tr>');
	$("#modalPendientes").modal("show");
	$.get(URL_CONTEO + "pendientes", { idconteo: conteo.idconteo }, function(resp){
		var r = appParseJson(resp, null);
		var items = (r && r.items) || [];
		$("#tblPendientes tbody").html(items.length ? items.map(function(a){
			return '<tr data-id="' + a.idarticulo + '"><td><strong>' + esc(a.nombre) + '</strong> <small class="text-soft">' + esc(a.codigo) + "</small></td><td>" + esc(a.categoria) +
				'</td><td class="text-right">' + cant(a.stock) + " " + esc(a.unidad) + '</td><td class="text-right">' + money(a.valor) + "</td>" +
				'<td class="text-right"><button type="button" class="btn btn-primary btn-xs btn-contar-pend" title="Escribir cuántos hay"><i class="fa fa-pencil"></i> Contar</button> ' +
				'<button type="button" class="btn btn-default btn-xs btn-cero-pend" title="No hay ninguno en el almacén"><i class="fa fa-ban"></i> No hay</button></td></tr>';
		}).join("") : '<tr><td colspan="5" class="text-center text-success" style="padding:20px"><i class="fa fa-check-circle"></i> Todo lo que tiene stock ya fue contado.</td></tr>');
	});
}

function abrirAplicar(){
	$("#apCero").prop("checked", false);
	$("#apSinPermiso").toggle(!puedeAplicar);
	$("#btnAplicar").prop("disabled", !puedeAplicar);
	$("#apCeroTxt").text("(" + $("#rPendientes").text() + " producto(s) sin contar con stock)");
	cargarPlan();
	$("#modalAplicar").modal("show");
}

function cargarPlan(){
	$("#tblPlan tbody").html('<tr><td colspan="6" class="text-center text-soft">Calculando…</td></tr>');
	$.get(URL_CONTEO + "previsualizar", { idconteo: conteo.idconteo, cero: $("#apCero").is(":checked") ? 1 : 0 }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { $("#tblPlan tbody").html('<tr><td colspan="6" class="text-danger">' + esc((r && r.message) || "No se pudo calcular.") + "</td></tr>"); return; }
		var movs = r.movimientos || [];
		$("#apAjustes").text(movs.length);
		$("#apSobrante").text(money(r.valor_sobrante));
		$("#apFaltante").text(money(r.valor_faltante));
		$("#tblPlan tbody").html(movs.length ? movs.map(function(m){
			return "<tr><td>" + esc(m.nombre) + "</td><td>" + esc(m.detalle) + "</td><td>" +
				(m.tipo === "ENTRADA" ? '<span class="label bg-green">ENTRADA</span>' : '<span class="label bg-red">SALIDA</span>') +
				'</td><td class="text-right">' + cant(m.cantidad) + " " + esc(m.unidad) + '</td><td class="text-right">' + signo(m.valor, money(Math.abs(m.valor))) +
				'</td><td><small class="text-soft">' + esc(m.nota) + "</small></td></tr>";
		}).join("") : '<tr><td colspan="6" class="text-center text-success" style="padding:18px"><i class="fa fa-check-circle"></i> Lo contado coincide con el sistema: no habrá ajustes.</td></tr>');
	});
}

function aplicar(){
	var cero = $("#apCero").is(":checked");
	var msg = "Se harán los ajustes de inventario de la lista y el conteo quedará cerrado." + (cero ? "<br><strong>Lo que no se contó quedará en cero.</strong>" : "");
	appConfirm(msg, function(){
		appSetLoading("#btnAplicar", true);
		$.post(URL_CONTEO + "aplicar", { idconteo: conteo.idconteo, cero: cero ? 1 : 0 }, function(resp){
			appSetLoading("#btnAplicar", false);
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "", r.ok ? 7000 : 8000);
			if (!r.ok) { return; }
			var id = conteo.idconteo;
			$("#modalAplicar").modal("hide");
			conteo = null;
			lineas = [];
			loteRecordado = {};
			pintarRecordados();
			cargarEstado();
			if (tablaConteos) { tablaConteos.ajax.reload(null, false); }
			window.open("../reportes/rptconteo.php?id=" + id, "_blank");
			if (window.cargarAlertasBell) { window.cargarAlertasBell(); }
		}).fail(function(){ appSetLoading("#btnAplicar", false); });
	}, { titulo: "Aplicar conteo", ok: "Aplicar ajustes", tipo: "success" });
}

function anular(){
	appConfirm("El conteo se cancelará y el stock <strong>no</strong> se modificará. Lo contado quedará en el historial.", function(){
		$.post(URL_CONTEO + "anular", { idconteo: conteo.idconteo }, function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "");
			if (r.ok) { conteo = null; lineas = []; loteRecordado = {}; pintarRecordados(); cargarEstado(); if (tablaConteos) { tablaConteos.ajax.reload(null, false); } }
		});
	}, { titulo: "Anular conteo", ok: "Anular", tipo: "danger" });
}

// ---------------------------------------------------------------------
// Historial
// ---------------------------------------------------------------------

function listarConteos(){
	tablaConteos = $("#tblConteos").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 10,
		dom: "Bfrtip",
		buttons: window.appDataTableButtons("Conteos de inventario", true),
		order: [[1, "desc"]],
		columnDefs: [{ targets: 1, render: window.appOrdenPorDato }],
		ajax: { url: URL_CONTEO + "listar", type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } }
	});
}

function verConteo(id){
	$("#tblVer tbody").html('<tr><td colspan="7" class="text-center text-soft">Cargando…</td></tr>');
	$("#verReporte").attr("href", "../reportes/rptconteo.php?id=" + id);
	$("#modalVer").modal("show");
	$.get(URL_CONTEO + "lineas", { idconteo: id }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		var c = r.conteo;
		$("#verTitulo").html('<i class="fa fa-barcode"></i> ' + esc(c.nombre) + ' <span class="label label-default">' + esc(c.estado) + "</span>");
		$("#verDatos").text((c.categoria || "Todo el almacén") + (c.por_lote ? " · por lote" : "") + " · inició " + c.usuario + " el " + c.fecha_inicio);
		var ls = r.lineas || [];
		$("#tblVer tbody").html(ls.length ? ls.map(function(l){
			var aplicado = l.diferencia_aplicada === null ? '<span class="text-soft">—</span>' : signo(l.diferencia_aplicada);
			return "<tr><td>" + esc(l.nombre) + "</td><td>" + detalleLinea(l) + '</td><td class="text-right">' + cant(l.cantidad) + '</td><td class="text-right">' + cant(l.stock_sistema) +
				'</td><td class="text-right">' + signo(l.diferencia) + '</td><td class="text-right">' + aplicado + '</td><td class="text-right">' + (l.valor === 0 ? "—" : signo(l.valor, money(Math.abs(l.valor)))) + "</td></tr>";
		}).join("") : '<tr><td colspan="7" class="text-center text-soft">Sin lecturas.</td></tr>');
	});
}

// ---------------------------------------------------------------------

function init(){
	listarConteos();
	cargarEstado();

	$("#formNuevoConteo").on("submit", function(e){
		e.preventDefault();
		appSetLoading("#btnEmpezar", true);
		$.post(URL_CONTEO + "crear", $(this).serialize(), function(resp){
			appSetLoading("#btnEmpezar", false);
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "");
			if (r.ok) { $("#formNuevoConteo")[0].reset(); cargarEstado(); tablaConteos.ajax.reload(null, false); }
		}).fail(function(){ appSetLoading("#btnEmpezar", false); });
	});

	// Lector: con Enter, con Tab o sin sufijo (rafaga)
	window.appLectorCodigo("#cScan", leer);
	$("#btnScan").on("click", function(){ leer($("#cScan").val()); });
	$("#cCantidad").on("keydown", function(e){ if (e.key === "Enter") { e.preventDefault(); $("#cScan").focus(); } });

	// Corregir lo contado en la tabla
	$("#tblLineas").on("keydown", ".conteo-input", function(e){
		if (e.key === "Enter") { e.preventDefault(); $(this).trigger("change"); $("#cScan").focus(); }
	});
	$("#tblLineas").on("change", ".conteo-input", function(){
		var id = parseInt($(this).data("id"), 10);
		var l = lineas.find(function(x){ return x.iddetalle === id; });
		var valor = parseFloat(String($(this).val()).replace(",", "."));
		if (!l || !(valor >= 0) || valor === l.cantidad) { return; }
		registrar({ idarticulo: l.idarticulo, idvariante: l.idvariante, idlote: l.idlote, lote_codigo: l.tipo_lote === "nuevo" ? (l.lote_codigo || "") : "",
			lote_vencimiento: l.tipo_lote === "nuevo" ? (l.lote_vencimiento || "") : "", cantidad: valor, modo: "fijar" }, "", null);
	});
	$("#tblLineas").on("click", ".btn-quitar", function(){
		var id = parseInt($(this).data("id"), 10);
		appConfirm("¿Quitar esta línea? Lo contado en ella se borra (como si no se hubiera contado).", function(){
			$.post(URL_CONTEO + "eliminarLinea", { idconteo: conteo.idconteo, iddetalle: id }, function(resp){
				var r = appParseJson(resp, { ok: false, message: resp });
				appNotify(r.ok ? "success" : "error", r.message || "");
				if (r.ok) { lineas = lineas.filter(function(l){ return l.iddetalle !== id; }); pintarLineas(); resumenDiferido(); }
			});
		}, { titulo: "Quitar línea", ok: "Quitar", tipo: "danger" });
	});
	$("#fLineas").on("input", appDebounce(pintarLineas, 150));
	$("#fDif").on("change", pintarLineas);

	// Ventana de eleccion: clic o tecla numerica
	$("#elegirOpciones").on("click", ".conteo-opcion", function(){
		var o = $("#elegirOpciones").data("opciones")[parseInt($(this).data("i"), 10)];
		cerrarEleccion(o.valor, o.texto);
	});
	// Una tecla numerica elige la opcion, pero si llegan mas teclas casi juntas es
	// el lector escaneando otro producto: se ignora para no elegir por accidente
	var teclaPendiente = null;
	var ultimaTecla = 0;
	$("#modalElegir").on("keydown", function(e){
		if ($(e.target).is("input")) {
			if (e.key === "Enter" && $(e.target).closest("#elegirLoteNuevo").length) { e.preventDefault(); $("#btnLoteNuevo").click(); }
			return;
		}
		var ahora = Date.now();
		var rafaga = ahora - ultimaTecla < 60;
		ultimaTecla = ahora;
		if (rafaga) {
			clearTimeout(teclaPendiente);
			teclaPendiente = null;
			if (e.key.length === 1 || e.key === "Enter") { e.preventDefault(); }
			return;
		}
		var n = parseInt(e.key, 10);
		var ops = $("#elegirOpciones").data("opciones") || [];
		if (n >= 1 && n <= Math.min(9, ops.length)) {
			e.preventDefault();
			teclaPendiente = setTimeout(function(){ teclaPendiente = null; cerrarEleccion(ops[n - 1].valor, ops[n - 1].texto); }, 90);
		}
	});
	$("#btnLoteNuevo").on("click", function(){
		var codigo = $.trim($("#lnCodigo").val());
		var vence = $("#lnVence").val();
		if (!codigo && !vence) { appNotify("warning", "Escribe el código o la fecha de vencimiento del lote."); return; }
		cerrarEleccion({ lote_codigo: codigo, lote_vencimiento: vence }, (codigo || "Lote sin código") + (vence ? " · vence " + fecha(vence) : ""));
	});
	$("#modalElegir").on("shown.bs.modal", function(){ $("#elegirOpciones .conteo-opcion").first().focus(); });
	$("#modalElegir").on("hidden.bs.modal", function(){
		if (eleccion) { var cb = eleccion; eleccion = null; cb(null); }
		pausado = false;
		$("#cScan").focus();
		procesarCola();
	});

	$(document).on("click", ".olvidar-lote", function(e){
		e.preventDefault();
		delete loteRecordado[$(this).data("id")];
		pintarRecordados();
		$("#cScan").focus();
	});

	// Buscar por nombre
	$("#buscarTexto").on("input", buscarDiferido);
	$("#buscarResultados").on("click", ".conteo-sugerencia", function(e){
		e.preventDefault();
		var item = pendienteBuscar;
		pendienteBuscar = null;
		var id = $(this).data("id");
		$("#modalBuscar").modal("hide");
		contarArticulo(id, item ? item.cantidad : 1, "sumar");
	});
	$("#modalBuscar").on("shown.bs.modal", function(){ $("#buscarTexto").focus(); });
	$("#modalBuscar").on("hidden.bs.modal", function(){ pendienteBuscar = null; pausado = false; $("#cScan").focus(); procesarCola(); });

	// Pendientes
	$("#btnPendientes").on("click", abrirPendientes);
	$("#tblPendientes").on("click", ".btn-contar-pend", function(){
		var $tr = $(this).closest("tr");
		var id = $tr.data("id");
		bootbox.prompt({ title: "¿Cuántos hay de " + esc($tr.find("strong").text()) + "?", inputType: "number", value: "", callback: function(v){
			if (v === null || v === "") { return; }
			var n = parseFloat(String(v).replace(",", "."));
			if (!(n >= 0)) { appNotify("warning", "Cantidad no válida."); return; }
			$tr.remove();
			contarArticulo(id, n, "fijar");
		} });
	});
	$("#tblPendientes").on("click", ".btn-cero-pend", function(){
		var $tr = $(this).closest("tr");
		$tr.hide();
		contarArticulo($tr.data("id"), 0, "fijar", true, function(){ $tr.show(); });
	});
	$("#modalPendientes").on("hidden.bs.modal", function(){ $("#cScan").focus(); });

	$("#btnActualizar").on("click", cargarLineas);
	$("#btnRevisar").on("click", abrirAplicar);
	$("#apCero").on("change", cargarPlan);
	$("#btnAplicar").on("click", aplicar);
	$("#btnAnular").on("click", anular);
	$("#modalAplicar").on("hidden.bs.modal", function(){ $("#cScan").focus(); });

	// Lo que cuentan otras personas: se refresca solo si nadie esta escribiendo
	setInterval(function(){
		if (conteo && !pausado && !procesando && !$(".modal.in").length && !$("#cScan").val() && !$(document.activeElement).is(".conteo-input") && document.visibilityState === "visible") {
			cargarLineas();
		}
	}, 30000);
}

init();
