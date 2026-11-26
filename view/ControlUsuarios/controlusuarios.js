/* Control de cuentas (Informática): estado de contraseñas y contraseñas temporales.
   Las contraseñas nunca llegan al navegador; solo la temporal recién generada, una vez. */
var cuentas = [];
var esAdmin = $('#admin_idx').val() === '1';
var ahora = null;
var lista;
var ICONO_LLAVE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 9.2-9.2M17 6l3 3M14.5 8.5l2 2"/></svg>';

var EVENTOS = {
    temporal_generada: 'Temporal generada',
    pass_creada: 'Creó su contraseña',
    pass_cambiada: 'Cambió su contraseña',
    cuenta_creada: 'Cuenta creada',
    vista_como: 'Un administrador vio su cuenta'
};

$(function () {
    lista = Copeba.listado('#lista_cuentas', {
        id: 'cuentas',
        titulo: 'Cuentas',
        descripcion: 'Si alguien olvidó su contraseña, genera una temporal y díctasela: al entrar deberá crear la suya.',
        icono: ICONO_LLAVE,
        pestanas: [
            { texto: 'Todas', filtro: null },
            { texto: 'Temporal pendiente', filtro: function (c) { return c.temporal && !c.vencida; } },
            { texto: 'Temporal vencida', filtro: function (c) { return c.temporal && c.vencida; } },
            { texto: 'Con contraseña propia', filtro: function (c) { return !c.temporal; } }
        ],
        placeholder: 'Buscar por nombre, usuario, área o sede…',
        buscar: function (c) { return [c.nombre, c.usuario, c.areas, c.sede].join(' '); },
        columnas: [
            { titulo: 'Persona', render: function (c) {
                return $('<span>').append($('<strong>').text(c.nombre), c.admin ? $('<small class="cp-suave cp-bloque">').text('Administrador del sistema') : null);
            } },
            { titulo: 'Usuario', clase: 'cp-mono', render: function (c) { return c.usuario; } },
            { titulo: 'Área · Sede', render: function (c) {
                return $('<span>').append($('<span>').text(c.areas || 'Sin área'), $('<small class="cp-suave cp-bloque">').text(c.sede));
            } },
            { titulo: 'Contraseña', render: estadoPass },
            { titulo: 'Último movimiento', orden: function (c) { return c.ultimo ? c.ultimo.fecha : ''; }, render: function (c) {
                if (!c.ultimo) { return $('<span class="cp-suave">').text('—'); }
                return $('<span>').append(
                    $('<span>').text(EVENTOS[c.ultimo.evento] || c.ultimo.evento),
                    $('<small class="cp-suave cp-bloque">').text(Copeba.fecha(c.ultimo.fecha) + ' ' + Copeba.hora(c.ultimo.fecha) + (c.ultimo.actor ? ' · ' + c.ultimo.actor : '')));
            } },
            { titulo: 'Acciones', orden: false, clase: 'cp-der cp-acciones-fila', render: function (c) {
                return $('<span>').append(
                    (esAdmin && !c.admin) ? $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">').attr({ 'data-accion': 'vercomo', 'data-id': c.id, title: 'Ver el sistema como esta persona (solo lectura)' }).text('Ver como') : null,
                    $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">').attr({ 'data-accion': 'historial', 'data-id': c.id }).text('Historial'),
                    $('<button type="button" class="cp-btn cp-btn-primario cp-btn-chico">').attr({ 'data-accion': 'temporal', 'data-id': c.id }).text('Contraseña temporal'));
            } }
        ],
        vacio: 'No hay cuentas.',
        total: 'Total de cuentas',
        exportar: { archivo: 'cuentas', campos: [
            { titulo: 'Nombre', valor: function (c) { return c.nombre; } },
            { titulo: 'Usuario', valor: function (c) { return c.usuario; } },
            { titulo: 'Áreas', valor: function (c) { return c.areas; } },
            { titulo: 'Sede', valor: function (c) { return c.sede; } },
            { titulo: 'Contraseña', valor: function (c) { return c.temporal ? (c.vencida ? 'Temporal vencida' : 'Temporal pendiente') : 'Propia'; } },
            { titulo: 'Último cambio', valor: function (c) { return c.fech_pass || ''; } }
        ] }
    });
    cargar();

    $('#lista_cuentas').on('click', 'button[data-accion]', function () {
        var id = Number($(this).data('id'));
        var accion = $(this).data('accion');
        if (accion === 'temporal') { generar(id); }
        else if (accion === 'vercomo') { verComo(id); }
        else { verHistorial(id); }
    });
    $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
    $('#btn_copiar').on('click', copiar);
    $('#dlg_temporal').on('close', function () { $('#txt_temporal').text(''); });  /* no dejarla en pantalla */
});

/* "Ver como" (solo Administrador del sistema; el servidor lo vuelve a comprobar) */
function verComo(id) {
    var c = cuentas.filter(function (x) { return x.id === id; })[0];
    Copeba.confirmar('¿Ver el sistema como ' + (c ? c.nombre : 'esta persona') + '? Verás sus pantallas en modo solo lectura; quedará registrado.', function () {
        Copeba.enviar('../../controller/cuenta.php?op=ver_como', { usu_id: id }, 'No se pudo entrar en "Ver como".')
            .done(function () { location.href = '../Home/'; });
    });
}

function cargar() {
    $.post('../../controller/usuario.php?op=control_listar').done(function (r) {
        r = Copeba.leer(r) || {};
        cuentas = r.data || [];
        ahora = r.ahora;
        pintarResumen();
        lista.datos(cuentas);
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
