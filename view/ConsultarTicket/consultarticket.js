/* Lista de tickets con Copeba.listado. Quien atiende tickets ve todos y puede asignar. */
var atiende = $('#soporte_idx').val() === '1';
var lista;

var ICONO_TICKET = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/></svg>';
var OJO = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';

function esteMes(fecha) {
    var hoy = new Date();
    return fecha && fecha.substr(0, 7) === hoy.getFullYear() + '-' + ('0' + (hoy.getMonth() + 1)).slice(-2);
}

$(function () {
    var pestanas = [
        { texto: 'Todos', filtro: null },
        { texto: 'Abiertos', filtro: function (t) { return t.estado === 'Abierto'; } },
        { texto: 'En espera', filtro: function (t) { return t.estado === 'En espera'; } },
        { texto: 'Cerrados', filtro: function (t) { return t.estado === 'Cerrado'; } }
    ];
    if (atiende) {
        pestanas.splice(1, 0, { texto: 'Mis asignados', filtro: function (t) { return t.mio && t.estado !== 'Cerrado'; } });
        pestanas.splice(2, 0, { texto: 'Sin asignar', filtro: function (t) { return !t.asignado && t.estado !== 'Cerrado'; } });
    }
    pestanas.push({ texto: 'Este mes', filtro: function (t) { return esteMes(t.fecha); } });

    var columnas = [
        { titulo: 'Folio', clase: 'cp-mono', orden: function (t) { return t.id; }, render: function (t) { return Copeba.folio(t.id); } },
        { titulo: 'Título', render: function (t) {
            return $('<strong class="cp-tarea-nombre">').text(t.titulo);   /* se abre con el ojo */
        } },
        { titulo: 'Categoría', clase: 'cp-suave', render: function (t) { return t.categoria; } },
        { titulo: 'Urgencia', orden: function (t) { return Number(t.prio_id); }, render: function (t) { return $('<span class="cp-prioridad">').addClass('p' + t.prio_id).text(t.prioridad); } },
        { titulo: 'Estado', render: function (t) {
            var chip = Copeba.estado(t.estado, t.vencido);
            if (atiende && t.estado === 'Cerrado') {    /* reabrir desde la lista */
                return $('<button type="button" class="cp-chip-boton" title="Reabrir">').attr('data-reabrir', t.id).append(chip);
            }
            return chip;
        } },
        { titulo: 'Soporte', render: function (t) {
            if (t.asignado) { return t.asignado; }
            if (atiende && t.estado !== 'Cerrado') {
                return $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">').attr('data-asignar', t.id).text('Asignar');
            }
            return $('<span class="cp-estado pendiente">').text('Pendiente');
        } },
        { titulo: 'Creado', clase: 'cp-mono', orden: function (t) { return t.fecha; }, render: function (t) { return Copeba.fecha(t.fecha); } },
        { titulo: '', clase: 'cp-der', render: function (t) {
            return $('<a class="cp-ver">').attr({ href: '../DetalleTicket/?ID=' + encodeURIComponent(t.id), title: 'Ver ticket', 'aria-label': 'Ver ticket ' + Copeba.folio(t.id) }).html(OJO);
        } }
    ];
    if (atiende) {
        columnas.splice(2, 0, { titulo: 'Solicitante', render: function (t) { return t.solicitante; } });
    }

    lista = Copeba.listado('#lista_tickets', {
        id: 'tickets',
        titulo: atiende ? 'Solicitudes' : 'Mis solicitudes',
        descripcion: 'Consulta y da seguimiento a los tickets de soporte.',
        icono: ICONO_TICKET,
        boton: { texto: 'Nuevo ticket', accion: function () { location.href = '../NuevoTicket/'; } },
        pestanas: pestanas,
        placeholder: 'Buscar por folio, título, categoría, solicitante o técnico…',
        buscar: function (t) { return [Copeba.folio(t.id), t.id, t.titulo, t.categoria, t.prioridad, t.estado, t.solicitante, t.asignado].join(' '); },
        columnas: columnas,
        vacio: atiende ? 'No hay tickets.' : 'Todavía no has reportado tickets.',
        total: 'Total de tickets',
        exportar: { archivo: 'tickets', campos: [
            { titulo: 'Folio', valor: function (t) { return t.id; } },
            { titulo: 'Título', valor: function (t) { return t.titulo; } },
            { titulo: 'Solicitante', valor: function (t) { return t.solicitante; } },
            { titulo: 'Categoría', valor: function (t) { return t.categoria; } },
            { titulo: 'Urgencia', valor: function (t) { return t.prioridad; } },
            { titulo: 'Estado', valor: function (t) { return t.estado; } },
            { titulo: 'Técnico', valor: function (t) { return t.asignado; } },
            { titulo: 'Creado', valor: function (t) { return t.fecha; } }
        ] }
    });
    cargar();

    if (atiende) {
        $.post('../../controller/usuario.php?op=combo', function (html) { $('#usu_asig').html(html); });
        $('#lista_tickets').on('click', '[data-asignar]', function () { abrirAsignar(Number($(this).data('asignar'))); });
        $('#lista_tickets').on('click', '[data-reabrir]', function () { reabrir(Number($(this).data('reabrir'))); });
        $('#asignar_form').on('submit', asignar);
        $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
    }
});

function cargar() {
    $.post('../../controller/ticket.php?op=lista').done(function (r) {
        lista.datos((Copeba.leer(r) || {}).data || []);
    });
}

function abrirAsignar(id) {
    var t = lista.filas().filter(function (x) { return x.id === id; })[0];
    $('#tick_id').val(id);
    $('#usu_asig').val('');
    $('#dlg_asignar_ticket').text(Copeba.folio(id) + ' · ' + (t ? t.titulo : ''));
    Copeba.dialogo('dlg_asignar');
}

function asignar(e) {
    e.preventDefault();
    if (!$('#usu_asig').val()) { Copeba.aviso('Elige el técnico.'); return; }
    var tick_id = $('#tick_id').val();
    Copeba.enviar('../../controller/ticket.php?op=asignar', new FormData($('#asignar_form')[0]), 'No se pudo asignar.')
        .done(function () {
            $.post('../../controller/email.php?op=ticket_asignado', { tick_id: tick_id });
            Copeba.dialogo('dlg_asignar', false);
            cargar();
            Copeba.exito('Ticket asignado.');
        });
}

function reabrir(id) {
    Copeba.confirmar('¿Reabrir el ticket ' + Copeba.folio(id) + '?', function () {
        Copeba.enviar('../../controller/ticket.php?op=reabrir', { tick_id: id }, 'No se pudo reabrir.')
            .done(function () { cargar(); Copeba.exito('Ticket reabierto.'); });
    });
}
