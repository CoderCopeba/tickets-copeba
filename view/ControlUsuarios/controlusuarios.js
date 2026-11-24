/* Control de cuentas (Informática): estado de contraseñas y contraseñas temporales.
   Las contraseñas nunca llegan al navegador; solo la temporal recién generada, una vez. */
var cuentas = [];
var ahora = null;

var EVENTOS = {
    temporal_generada: 'Temporal generada',
    pass_creada: 'Creó su contraseña',
    pass_cambiada: 'Cambió su contraseña',
    cuenta_creada: 'Cuenta creada'
};

$(function () {
    cargar();
    $('#buscar').on('input', pintar);
    $('#cuentas').on('click', 'button[data-accion]', function () {
        var id = Number($(this).data('id'));
        if ($(this).data('accion') === 'temporal') { generar(id); } else { verHistorial(id); }
    });
    $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
    $('#btn_copiar').on('click', copiar);
    $('#dlg_temporal').on('close', function () { $('#txt_temporal').text(''); });  /* no dejarla en pantalla */
});

function cargar() {
    $.post('../../controller/usuario.php?op=control_listar').done(function (r) {
        r = Copeba.leer(r) || {};
        cuentas = r.data || [];
        ahora = r.ahora;
        pintarResumen();
        pintar();
    });
}

function horasEntre(desde, hasta) {
    return Math.round((new Date(hasta.replace(' ', 'T')) - new Date(desde.replace(' ', 'T'))) / 3600000);
}

/* Estado de la contraseña, en palabras */
function estadoPass(c) {
    if (c.temporal && c.vencida) { return $('<span class="cp-estado cerrado">').text('Temporal vencida'); }
    if (c.temporal) {
        var h = Math.max(0, horasEntre(ahora, c.expira));
        var plazo = h < 1 ? 'menos de 1 h' : (h > 48 ? Math.round(h / 24) + ' días' : h + ' h');
        return $('<span class="cp-estado pendiente">').text('Temporal · vence en ' + plazo);
    }
    if (c.fech_pass) { return $('<span class="cp-estado abierto">').text('Propia · desde ' + Copeba.fecha(c.fech_pass)); }
    return $('<span class="cp-suave">').text('Propia');
}

function pintarResumen() {
    var temporales = cuentas.filter(function (c) { return c.temporal && !c.vencida; }).length;
    var vencidas = cuentas.filter(function (c) { return c.temporal && c.vencida; }).length;
    var kpis = [['Cuentas activas', cuentas.length, ''], ['Temporales pendientes', temporales, 'ambar'], ['Temporales vencidas', vencidas, 'gris']];
    var $r = $('#resumen').empty();
    $.each(kpis, function (_, k) {
        $r.append($('<article class="cp-tarjeta cp-kpi">').append(
            $('<div class="cp-kpi-cab">').append($('<span>').text(k[0])),
            $('<div class="cp-kpi-pie">').append($('<span class="cp-kpi-num">').text(k[1]))
        ));
    });
}

function pintar() {
    var q = $.trim($('#buscar').val()).toLowerCase();
    var $t = $('#cuentas').empty();
    var filas = cuentas.filter(function (c) {
        return !q || [c.nombre, c.usuario, c.areas, c.sede].join(' ').toLowerCase().indexOf(q) !== -1;
    });
    if (!filas.length) {
        $t.append($('<tr>').append($('<td colspan="6" class="cp-vacio">').text(q ? 'Sin resultados.' : 'No hay cuentas.')));
        return;
    }
    $.each(filas, function (_, c) {
        var ultimo = c.ultimo
            ? $('<span>').append(
                $('<span>').text(EVENTOS[c.ultimo.evento] || c.ultimo.evento),
                $('<small class="cp-suave cp-bloque">').text(Copeba.fecha(c.ultimo.fecha) + ' ' + Copeba.hora(c.ultimo.fecha) + (c.ultimo.actor ? ' · ' + c.ultimo.actor : '')))
            : $('<span class="cp-suave">').text('—');
        $t.append($('<tr>').append(
            $('<td>').append($('<strong>').text(c.nombre), c.admin ? $('<small class="cp-suave cp-bloque">').text('Administrador del sistema') : null),
            $('<td class="cp-mono">').text(c.usuario),
            $('<td>').append($('<span>').text(c.areas || 'Sin área'), $('<small class="cp-suave cp-bloque">').text(c.sede)),
            $('<td>').append(estadoPass(c)),
            $('<td>').append(ultimo),
            $('<td class="cp-der cp-acciones-fila">').append(
                $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">').attr({ 'data-accion': 'historial', 'data-id': c.id }).text('Historial'),
                $('<button type="button" class="cp-btn cp-btn-primario cp-btn-chico">').attr({ 'data-accion': 'temporal', 'data-id': c.id }).text('Contraseña temporal')
            )
        ));
    });
}

function generar(id) {
    var c = cuentas.filter(function (x) { return x.id === id; })[0];
    Copeba.confirmar('¿Generar una contraseña temporal para ' + (c ? c.nombre : 'esta persona') + '? Su contraseña actual dejará de funcionar.', function () {
        Copeba.enviar('../../controller/usuario.php?op=generar_temporal', { usu_id: id }, 'No se pudo generar la contraseña.')
            .done(function (r) {
                swal.close();
                $('#dlg_temporal_para').text('Para ' + r.nombre + ' (' + (c ? c.usuario : '') + ')');
                $('#txt_temporal').text(r.temporal);
                $('#txt_horas').text(r.horas);
                Copeba.dialogo('dlg_temporal');
                cargar();
            });
    });
}

function copiar() {
    var texto = $('#txt_temporal').text();
    if (!navigator.clipboard) { Copeba.aviso('Tu navegador no permite copiar automáticamente. Selecciona el texto.'); return; }
    navigator.clipboard.writeText(texto).then(function () {
        $('#btn_copiar').text('Copiada');
        setTimeout(function () { $('#btn_copiar').text('Copiar'); }, 1500);
    });
}

function verHistorial(id) {
    var c = cuentas.filter(function (x) { return x.id === id; })[0];
    $('#dlg_historial_titulo').text('Historial de ' + (c ? c.nombre : 'la cuenta'));
    $.post('../../controller/usuario.php?op=historial', { usu_id: id }).done(function (r) {
        var lista = Copeba.leer(r) || [];
        var $l = $('#historial').empty();
        if (!lista.length) { $l.append($('<li class="cp-vacio">').text('Sin movimientos registrados.')); }
        $.each(lista, function (_, h) {
            $l.append($('<li>').append($('<div>').append(
                $('<strong>').text(EVENTOS[h.evento] || h.evento),
                $('<small class="cp-suave cp-bloque">').text([Copeba.fecha(h.fecha) + ' ' + Copeba.hora(h.fecha), h.actor ? 'por ' + h.actor : '', h.detalle].filter(Boolean).join(' · '))
            )));
        });
        Copeba.dialogo('dlg_historial');
    });
}
