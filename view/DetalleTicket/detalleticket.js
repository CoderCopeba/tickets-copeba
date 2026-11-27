/* Detalle del ticket: una sola petición trae todo y se pinta según el rol.
   Los textos se insertan con .text(); el HTML de mensajes ya viene filtrado por el servidor. */
var tick_id = $('body').data('ticket');
var tipoRespuesta = 'respuesta';    /* 'respuesta' (visible al usuario) o 'nota' (interna TI) */
var adjuntos = [];
var ADJ_MAX = 10 * 1024 * 1024;
var ADJ_TIPOS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

$(function () {
    Copeba.editor('#tickd_descrip', 110, {
        barra: 'simple',
        ayuda: 'Escribe una actualización, diagnóstico o evidencia de solución...'
    });

    /* Volver a la pantalla anterior si venimos del sistema; si no, a la lista */
    $('#btn_volver').on('click', function (e) {
        if (document.referrer.indexOf(location.host) !== -1 && history.length > 1) {
            e.preventDefault();
            history.back();
        }
    });

    $('.cp-pestanas').on('click', 'button', function () {
        tipoRespuesta = $(this).data('tipo');
        $(this).addClass('activa').attr('aria-selected', 'true')
            .siblings().removeClass('activa').attr('aria-selected', 'false');
        var nota = tipoRespuesta === 'nota';
        $('#frm_responder').toggleClass('cp-modo-nota', nota);
        $('#aviso_nota').prop('hidden', !nota);
        $('#btn_enviar span').text(nota ? 'Guardar nota' : 'Enviar respuesta');
    });

    $('#adjuntos_respuesta').on('change', function () {
        for (var i = 0; i < this.files.length; i++) {
            var f = this.files[i], ext = f.name.split('.').pop().toLowerCase();
            if (f.name.indexOf('.') === -1 || ADJ_TIPOS.indexOf(ext) === -1) { Copeba.aviso('Tipo de archivo no permitido: ' + f.name); continue; }
            if (f.size > ADJ_MAX) { Copeba.aviso('El archivo ' + f.name + ' supera el máximo de 10 MB.'); continue; }
            adjuntos.push(f);
        }
        this.value = '';
        pintarArchivos();
    });
    $('#lista_archivos').on('click', 'button', function () {
        adjuntos.splice(Number($(this).data('i')), 1);
        pintarArchivos();
    });

    $('#frm_responder').on('submit', responder);
    $('#acciones').on('click', '[data-accion]', function () { acciones[$(this).data('accion')](); });

    cargar();
});

/* ---------- Carga y pintado ---------- */
function cargar() {
    $.post('../../controller/ticket.php?op=detalle', { tick_id: tick_id })
        .done(function (r) {
            r = Copeba.leer(r);
            if (!r || !r.ticket) { Copeba.error('No se pudo cargar el ticket.'); return; }
            pintar(r);
        })
        .fail(function (xhr) {
            if (xhr.status === 401) { location.href = '../../index.php'; return; }
            Copeba.error('No se pudo cargar el ticket.');
        });
}

function pintar(r) {
    var t = r.ticket, p = r.permisos, cerrado = t.estado === 'Cerrado';

    document.title = 'Copeba · Ticket ' + Copeba.folio(t.id);
    $('#lbl_folio').text('TK-' + Copeba.folio(t.id).slice(1));
    $('#lbl_estado').empty().append(etiquetaEstado(t, r.ahora));
    $('#lbl_titulo').text(t.titulo);

    /* Solicitud original */
    $('#lbl_iniciales').text(Copeba.iniciales(t.solicitante));
    $('#lbl_solicitante').text(t.solicitante);
    $('#lbl_reportado').text('Reportado el ' + Copeba.fecha(t.fecha) + ' a las ' + Copeba.hora(t.fecha));
    $('#lbl_descripcion').html(t.html);
    pintarDocs($('#docs_ticket'), t.docs);

    /* Ficha */
    $('#f_categoria').text(t.categoria);
    $('#f_prioridad').empty().append(etiquetaSla(t, r.ahora));
    $('#f_asignado').text(t.asignado || 'Sin asignar').toggleClass('cp-suave', !t.asignado);
    $('#f_estado').empty().append(etiquetaEstado(t, r.ahora));
    $('#f_creado').text(Copeba.fecha(t.fecha) + ' ' + Copeba.hora(t.fecha));
    $('#f_cierre, #f_cierre_t').prop('hidden', !(cerrado && t.fech_cierre));
    if (cerrado && t.fech_cierre) { $('#f_cierre').text(Copeba.fecha(t.fech_cierre) + ' ' + Copeba.hora(t.fech_cierre)); }

    pintarBitacora(r.bitacora);
    pintarAcciones(t, p);

    $('#frm_responder').prop('hidden', cerrado);
    $('#aviso_cerrado').prop('hidden', !cerrado);
}

/* "En atención" = abierto y con técnico asignado */
function etiquetaEstado(t, ahora) {
    /* Abierto o en espera fuera de su tiempo objetivo: rojo */
    var vencido = t.estado !== 'Cerrado' && !!t.sla_limite && !!ahora && t.sla_limite < ahora;
    if (vencido) { return Copeba.estado(t.estado, true); }
    if (t.estado === 'Abierto' && t.asignado) {
        return Copeba.estado('Abierto').text('En atención');
    }
    return Copeba.estado(t.estado);
}

/* Prioridad + tiempo restante del SLA (o vencido) */
function etiquetaSla(t, ahora) {
    var $chip = $('<span class="cp-prioridad">').addClass('p' + t.prio_id);
    if (t.estado === 'Cerrado') { return $chip.text(t.prioridad); }
    var minutos = Math.round((new Date(t.sla_limite.replace(' ', 'T')) - new Date(ahora.replace(' ', 'T'))) / 60000);
    var vencido = minutos < 0;
    $chip.toggleClass('vencido', vencido);
    return $chip.text(t.prioridad + ' · ' + (vencido ? 'vencido hace ' : '') + duracion(Math.abs(minutos)))
        .attr('title', 'Tiempo objetivo: ' + t.sla_horas + ' h desde la creación');
}

function duracion(min) {
    if (min >= 1440) { return Math.floor(min / 1440) + 'd ' + Math.floor((min % 1440) / 60) + 'h'; }
    if (min >= 60) { return Math.floor(min / 60) + 'h ' + (min % 60) + 'm'; }
    return min + 'm';
}

function pintarDocs($donde, docs) {
    $donde.empty();
    $.each(docs || [], function (_, d) {
        $donde.append($('<a class="cp-doc" target="_blank" rel="noopener">')
            .attr('href', '../../controller/documento.php?op=ver&doc_id=' + encodeURIComponent(d.id))
            .append(ICONO_DOC, $('<span>').text(d.nombre)));
    });
}
var ICONO_DOC = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>';

function pintarBitacora(entradas) {
    var $b = $('#bitacora').empty();
    if (!entradas.length) {
        $b.append($('<p class="cp-vacio">').text('Todavía no hay respuestas.'));
        return;
    }
    $.each(entradas, function (_, e) {
        var cuando = Copeba.fecha(e.fecha) + ' ' + Copeba.hora(e.fecha);
        if (e.tipo === 'evento') {
            /* el texto del evento viene escapado: se muestra como texto plano */
            $b.append($('<div class="cp-evento">').append(
                $('<span>').text($('<div>').html(e.html).text() + ' · ' + cuando)));
            return;
        }
        var nota = e.tipo === 'nota';
        var titulo = nota ? 'Nota interna TI · ' + e.autor
                          : e.autor + (e.es_ti ? ' (Respuesta al usuario)' : ' (Solicitante)');
        var $docs = $('<div class="cp-docs">');
        pintarDocs($docs, e.docs);
        $b.append($('<article class="cp-entrada">')
            .addClass(nota ? 'nota' : (e.es_ti ? 'ti' : 'usuario'))
            .append(
                $('<header>').append($('<strong>').text(titulo), $('<time class="cp-mono">').attr('title', cuando).text(Copeba.hora(e.fecha))),
                $('<div class="cp-contenido">').html(e.html),
                $docs
            ));
    });
}

/* Botones de la cabecera según rol y estado */
function pintarAcciones(t, p) {
    var $a = $('#acciones').empty();
    function boton(accion, texto, clase) {
        return $('<button type="button" class="cp-btn">').addClass(clase).attr('data-accion', accion).text(texto);
    }
    if (t.estado === 'Cerrado') {
        $a.append(boton('reabrir', 'Reabrir ticket', 'cp-btn-borde'));
        return;
    }
    if (p.soporte) {
        if (!t.asignado_a_mi) { $a.append(boton('tomar', 'Tomar ticket', 'cp-btn-borde')); }
        $a.append(t.estado === 'En espera'
            ? boton('reanudar', 'Reanudar', 'cp-btn-borde')
            : boton('posponer', 'Posponer', 'cp-btn-borde'));
        $a.append(boton('cerrar', 'Cerrar ticket', 'cp-btn-primario'));
    } else if (p.propio) {
        $a.append(boton('cerrar', 'Marcar como resuelto', 'cp-btn-primario'));
    }
}

/* ---------- Acciones ---------- */
function ejecutar(op, datos, exito, porDefecto) {
    return Copeba.enviar('../../controller/ticket.php?op=' + op, $.extend({ tick_id: tick_id }, datos || {}), porDefecto)
        .done(function () {
            cargar();
            Copeba.exito(exito);
        });
}

var acciones = {
    tomar: function () {
        ejecutar('tomar', null, 'Ahora el ticket es tuyo.', 'No se pudo tomar el ticket.');
    },
    posponer: function () {
        Copeba.pedirTexto('¿Por qué se pospone el ticket?', 'Ej. Esperando repuesto de Compras', function (motivo) {
            ejecutar('posponer', { motivo: motivo }, 'Ticket en espera.', 'No se pudo posponer.');
        });
    },
    reanudar: function () {
        ejecutar('reanudar', null, 'Ticket reanudado.', 'No se pudo reanudar.');
    },
    cerrar: function () {
        Copeba.confirmar('¿Cerrar el ticket? Confirma que el problema quedó resuelto.', function () {
            ejecutar('cerrar', null, 'Ticket cerrado.', 'No se pudo cerrar el ticket.')
                .done(function () { $.post('../../controller/email.php?op=ticket_cerrado', { tick_id: tick_id }); });
        });
    },
    reabrir: function () {
        Copeba.confirmar('¿Reabrir el ticket?', function () {
            ejecutar('reabrir', null, 'Ticket reabierto.', 'No se pudo reabrir.');
        });
    }
};

/* ---------- Responder ---------- */
function pintarArchivos() {
    var $l = $('#lista_archivos').empty();
    $.each(adjuntos, function (i, f) {
        $l.append($('<li>').append(
            $('<span>').text(f.name),
            $('<button type="button">').attr({ 'data-i': i, title: 'Quitar', 'aria-label': 'Quitar ' + f.name }).text('✕')
        ));
    });
}

function responder(e) {
    e.preventDefault();
    if ($('#tickd_descrip').summernote('isEmpty')) {
        Copeba.aviso(tipoRespuesta === 'nota' ? 'Escribe la nota antes de guardarla.' : 'Escribe la respuesta antes de enviarla.');
        return;
    }
    var datos = new FormData();
    datos.append('tick_id', tick_id);
    datos.append('tipo', tipoRespuesta);
    datos.append('tickd_descrip', $('#tickd_descrip').val());
    $.each(adjuntos, function (_, f) { datos.append('files[]', f); });

    var $btn = $('#btn_enviar').prop('disabled', true);
    Copeba.enviar('../../controller/ticket.php?op=responder', datos, 'No se pudo enviar.')
        .done(function () {
            $('#tickd_descrip').summernote('reset');
            adjuntos = [];
            pintarArchivos();
            cargar();
            Copeba.exito(tipoRespuesta === 'nota' ? 'Nota interna guardada.' : 'Respuesta enviada.');
        })
        .always(function () { $btn.prop('disabled', false); });
}
