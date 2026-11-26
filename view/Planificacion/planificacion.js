/* Planificación semanal y mensual.
   - Mi plan: escribir actividades, entregar antes de que empiece el periodo y,
     durante el periodo, marcar cada una: hecho, no cumplido o pospuesto (con justificación).
   - Mi equipo (solo jefatura): quién entregó, avance y revisión.
   Todo texto de la base se inserta con .text(). */
var URL_PLAN = '../../controller/plan.php?op=';
var tipo = 'semana', fecha = hoyIso(), periodo = null, miPlan = null, listaEquipo = null, planViendo = null;
var accionJustificar = null;      /* { act, estado: 'no_cumplido' | 'posponer' } */

var DIAS_CORTOS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
var OJO = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
var ICONO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h2M14 14h2M8 18h2"/></svg>';

var ESTADO_ACT = {
    pendiente:   { texto: 'Pendiente',    clase: 'pendiente' },
    hecho:       { texto: 'Hecho',        clase: 'abierto' },
    no_cumplido: { texto: 'No cumplido',  clase: 'cerrado' },
    pospuesto:   { texto: 'Pospuesto',    clase: 'pendiente' }
};
var ESTADO_PLAN = {
    sin_plan:  { texto: 'Sin plan',  clase: 'cerrado' },
    borrador:  { texto: 'Borrador',  clase: 'pendiente' },
    entregado: { texto: 'Entregado', clase: 'abierto' },
    revisado:  { texto: 'Revisado',  clase: 'abierto' }
};

/* ---------- Fechas ---------- */
function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
function hoyIso() { return iso(new Date()); }
function deIso(v) { var p = String(v).substr(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
function sumarDias(v, n) { var d = deIso(v); d.setDate(d.getDate() + n); return iso(d); }
function diaCorto(v) { var d = deIso(v); return DIAS_CORTOS[d.getDay()] + ' ' + d.getDate(); }
function textoPeriodo(p) {
    if (!p) { return ''; }
    if (p.tipo === 'mes') { var d = deIso(p.inicio); return MESES[d.getMonth()].replace(/^./, function (c) { return c.toUpperCase(); }) + ' ' + d.getFullYear(); }
    return 'Semana del ' + Copeba.fecha(p.inicio).substr(0, 5) + ' al ' + Copeba.fecha(p.fin);
}
/* Primer día del periodo siguiente al de hoy */
function fechaProximo() {
    var d = new Date();
    if (tipo === 'mes') { return iso(new Date(d.getFullYear(), d.getMonth() + 1, 1)); }
    var faltan = 8 - (d.getDay() === 0 ? 7 : d.getDay());   /* días hasta el próximo lunes */
    d.setDate(d.getDate() + faltan);
    return iso(d);
}
function chip(mapa, clave) {
    var e = mapa[clave] || mapa[Object.keys(mapa)[0]];
    return $('<span class="cp-estado">').addClass(e.clase).text(e.texto);
}

$(function () {
    $('.cp-plan-tipo').on('click', 'button', function () {
        tipo = $(this).data('tipo');
        $(this).addClass('activa').attr('aria-selected', 'true').siblings().removeClass('activa').attr('aria-selected', 'false');
        cargar();
    });
    $('#btn_anterior').on('click', function () { if (periodo) { fecha = sumarDias(periodo.inicio, -1); cargar(); } });
    $('#btn_siguiente').on('click', function () { if (periodo) { fecha = sumarDias(periodo.fin, 1); cargar(); } });
    $('#btn_actual').on('click', function () { fecha = hoyIso(); cargar(); });
    $('#btn_proximo').on('click', function () { fecha = fechaProximo(); cargar(); });
    $('#nueva_form').on('submit', agregar);
    $('#just_form').on('submit', guardarJustificacion);
    $('#btn_revisar').on('click', revisar);
    $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
    $('#actividades').on('click', '[data-accion]', accionActividad);
    $('#lista_equipo').on('click', '[data-plan]', function () { verPlan(Number($(this).attr('data-plan'))); });
    $('#btn_desde_ticket').on('click', abrirTickets);
    $('#ticket_form').on('submit', agregarTicket);
    $('#tk_buscar').on('input', filtrarTickets);
    cargar();
});

/* ---------- Mi plan ---------- */
function cargar() {
    $.post(URL_PLAN + 'periodo', { tipo: tipo, fecha: fecha }).done(function (r) {
        var d = Copeba.leer(r) || {};
        periodo = d.periodo; miPlan = d.plan;
        $('#lbl_periodo').text(textoPeriodo(periodo));
        $('#btn_desde_ticket').prop('hidden', !d.soporte);
        pintarMiPlan(d.hoy);
        if (d.dirige) { cargarEquipo(); }
    });
}

function pintarMiPlan(hoy) {
    var p = miPlan, estado = p ? p.plan_estado : 'sin_plan';
    var $estado = $('#lbl_estado').empty().append(chip(ESTADO_PLAN, estado));
    if (p && p.tarde) { $estado.append(' ', $('<span class="cp-prioridad vencido">').text('Entregado tarde')); }

    var plazo = Copeba.fecha(periodo.plazo);
    var $plazo = $('#lbl_plazo').removeClass('cp-plan-atrasado');
    if (!p || estado === 'borrador') {
        $plazo.text('Entrégalo a más tardar el ' + plazo + '.');
        if (hoy > periodo.plazo) { $plazo.addClass('cp-plan-atrasado').text('El plazo de entrega era el ' + plazo + ': entrégalo cuanto antes.'); }
    } else {
        $plazo.text('Entregado el ' + Copeba.fecha(p.fech_entrega) + (estado === 'revisado' ? ' · revisado por ' + p.revisado_por : ''));
    }

    var $c = $('#comentario_jefe').empty();
    if (p && p.plan_comentario) { $c.append($('<p class="cp-tarea-nota">').text('Comentario de ' + (p.revisado_por || 'tu jefatura') + ': ' + p.plan_comentario)); }

    var acts = p ? p.actividades : [];
    var $l = $('#actividades').empty();
    if (!acts.length) { $l.append($('<li class="cp-vacio">').text('Todavía no hay actividades en este periodo.')); }
    acts.forEach(function (a) { $l.append(filaActividad(a, estado, true)); });

    /* Agregar: siempre que no esté revisado; después de entregar queda como "agregada después" */
    $('#nueva_form').prop('hidden', estado === 'revisado');
    $('#nueva_texto').attr('placeholder', estado === 'entregado'
        ? 'Imprevisto: se marcará como agregado después de entregar'
        : 'Nueva actividad, p. ej. Cambio de batería laptop de Caja Soloma');
    $('#nueva_dia').val('').trigger('cp-fecha');

    /* "Entregar plan" arriba, junto al estado (lejos de "Agregar" para no pulsarlo sin querer) */
    var $lugar = $('#lugar_entregar').empty();
    if (p) { $lugar.append(botonPdf(p.plan_id)); }
    if (estado === 'borrador' && acts.length) {
        $lugar.append($('<button type="button" class="cp-btn cp-btn-primario cp-btn-chico">').text('Entregar plan').on('click', entregar));
    }
    var $pie = $('#pie_plan').empty();
    if (estado !== 'sin_plan' && estado !== 'borrador') {
        $pie.append($('<span class="cp-plan-resumen">').text(resumen(acts)));
    }
}

/* Versión para imprimir o guardar como PDF (con firmas y sello), en otra pestaña */
function botonPdf(plan_id) {
    return $('<a class="cp-btn cp-btn-borde cp-btn-chico" target="_blank" rel="noopener">')
        .attr({ href: 'imprimir.php?plan=' + encodeURIComponent(plan_id), title: 'Abrir el plan para imprimirlo o guardarlo como PDF' })
        .text('Descargar PDF');
}

function resumen(acts) {
    var c = { hecho: 0, no_cumplido: 0, pospuesto: 0 };
    acts.forEach(function (a) { if (c[a.act_estado] !== undefined) { c[a.act_estado]++; } });
    return c.hecho + ' de ' + acts.length + ' hechas · ' + c.no_cumplido + ' no cumplidas · ' + c.pospuesto + ' pospuestas';
}

/* Una actividad. "propia" = mi plan (con botones); si no, solo lectura (jefatura) */
function filaActividad(a, estadoPlan, propia) {
    var $li = $('<li class="cp-plan-act">').attr('data-id', a.act_id);
    var $info = $('<div class="cp-plan-act-info">').append(
        $('<span class="cp-plan-dia">').text(a.act_dia ? diaCorto(a.act_dia) : 'Sin día'),
        $('<span class="cp-plan-texto">').text(a.act_texto));
    var $notas = $('<div class="cp-plan-notas">');
    if (a.act_tick_id) {
        /* Ticket del que viene: enlace al detalle y su estado actual */
        $notas.append($('<a class="cp-chip cp-plan-ticket">').attr({ href: '../DetalleTicket/?ID=' + encodeURIComponent(a.act_tick_id), title: a.tick_titulo || '' })
            .text('Ticket ' + Copeba.folio(a.act_tick_id) + ' · ' + (a.tick_estado || '')));
        if (a.tick_estado === 'Cerrado' && a.act_estado === 'pendiente' && propia) {
            $notas.append($('<span class="cp-plan-just">').text('El ticket ya se cerró: márcala como hecha.'));
        }
    }
    if (Number(a.act_extra)) { $notas.append($('<span class="cp-chip">').text('Agregada después')); }
    if (a.act_origen_id) { $notas.append($('<span class="cp-chip">').text('Viene pospuesta' + (a.origen_justificacion ? ': ' + a.origen_justificacion : ''))); }
    if (a.act_justificacion) { $notas.append($('<span class="cp-plan-just">').text(a.act_justificacion)); }
    $info.append($notas);

    var $acc = $('<div class="cp-tarea-acciones">').append(chip(ESTADO_ACT, a.act_estado));
    if (propia && estadoPlan === 'borrador') {
        $acc.append(boton('Editar', 'editar', 'borde'), boton('Quitar', 'quitar', 'borde'));
    } else if (propia && estadoPlan === 'entregado') {
        if (a.act_estado === 'pendiente') {
            $acc.append(boton('No cumplido', 'no_cumplido', 'borde'), boton('Posponer', 'posponer', 'borde'), boton('Hecho', 'hecho', 'primario'));
        } else if (a.act_estado === 'hecho' || a.act_estado === 'no_cumplido') {
            $acc.append(boton('Deshacer', 'pendiente', 'borde'));
        }
    }
    return $li.append($info, $acc);
}
function boton(texto, accion, estilo) {
    return $('<button type="button" class="cp-btn cp-btn-chico">').addClass('cp-btn-' + estilo).attr('data-accion', accion).text(texto);
}

function actividadPorId(id) {
    return (miPlan ? miPlan.actividades : []).filter(function (a) { return Number(a.act_id) === id; })[0];
}

function accionActividad() {
    var accion = $(this).attr('data-accion');
    var a = actividadPorId(Number($(this).closest('li').attr('data-id')));
    if (!a) { return; }
    if (accion === 'quitar') {
        Copeba.confirmar('¿Quitar "' + a.act_texto + '" del plan?', function () {
            Copeba.enviar(URL_PLAN + 'quitar', { act_id: a.act_id }, 'No se pudo quitar.').done(cargar);
        });
    } else if (accion === 'editar') {
        editarEnLinea($(this).closest('li'), a);
    } else if (accion === 'hecho' || accion === 'pendiente') {
        Copeba.enviar(URL_PLAN + 'marcar', { act_id: a.act_id, estado: accion }, 'No se pudo actualizar.').done(cargar);
    } else {
        abrirJustificar(a, accion);
    }
}

/* Editar el texto y el día ahí mismo (solo en borrador) */
function editarEnLinea($li, a) {
    var $texto = $('<input type="text" class="cp-control" maxlength="300">').val(a.act_texto);
    var $dia = $('<input type="date">').val(a.act_dia || '');
    var $form = $('<form class="cp-plan-nueva cp-plan-editar" novalidate>').append($texto, $dia,
        $('<button type="button" class="cp-btn cp-btn-borde">').text('Cancelar').on('click', cargar),
        $('<button type="submit" class="cp-btn cp-btn-primario">').text('Guardar'));
    $li.empty().append($form);
    Copeba.calendario($dia[0]);
    $texto.trigger('focus');
    $form.on('submit', function (e) {
        e.preventDefault();
        if (!$.trim($texto.val())) { Copeba.aviso('Escribe la actividad.'); return; }
        Copeba.enviar(URL_PLAN + 'editar', { act_id: a.act_id, texto: $texto.val(), dia: $dia.val() }, 'No se pudo guardar.').done(cargar);
    });
}

function agregar(e) {
    e.preventDefault();
    var texto = $.trim($('#nueva_texto').val());
    if (!texto) { Copeba.aviso('Escribe la actividad.'); return; }
    var dia = $('#nueva_dia').val();
    if (dia && (dia < periodo.inicio || dia > periodo.fin)) { Copeba.aviso('El día debe estar dentro del periodo (' + textoPeriodo(periodo) + ').'); return; }
    Copeba.enviar(URL_PLAN + 'agregar', { tipo: tipo, fecha: periodo.inicio, texto: texto, dia: dia }, 'No se pudo agregar.')
        .done(function () { $('#nueva_texto').val('').trigger('focus'); cargar(); });
}

function entregar() {
    Copeba.confirmar('¿Entregar el plan de ' + textoPeriodo(periodo).toLowerCase() + '? Después ya no podrás cambiar ni quitar actividades, solo marcar tu avance.', function () {
        Copeba.enviar(URL_PLAN + 'entregar', { tipo: tipo, fecha: periodo.inicio }, 'No se pudo entregar.').done(function (r) {
            cargar();
            Copeba.exito(r.tarde ? 'Plan entregado (fuera del plazo).' : 'Plan entregado.');
        });
    });
}

/* ---------- Traer un ticket al plan ---------- */
var ticketsDisponibles = [];

function abrirTickets() {
    if (miPlan && miPlan.plan_estado === 'revisado') { Copeba.aviso('El plan ya fue revisado.'); return; }
    $('#ticket_form')[0].reset();
    $('#tk_dia').val('').trigger('cp-fecha');
    $('#tk_lista').html('<p class="cp-suave">Cargando tickets…</p>');
    Copeba.dialogo('dlg_ticket');
    $.post(URL_PLAN + 'tickets').done(function (r) {
        ticketsDisponibles = Copeba.leer(r) || [];
        filtrarTickets();
    });
}

function filtrarTickets() {
    var q = $.trim($('#tk_buscar').val()).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    var elegido = $('#tk_lista input:checked').val();
    var $l = $('#tk_lista').empty();
    var filas = ticketsDisponibles.filter(function (t) {
        var texto = [Copeba.folio(t.tick_id), t.tick_titulo, t.sede_nom, t.solicitante].join(' ').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        return !q || texto.indexOf(q) !== -1;
    });
    if (!filas.length) { $l.html('<p class="cp-suave">' + (ticketsDisponibles.length ? 'Ningún ticket coincide.' : 'No tienes tickets abiertos ni hay tickets sin técnico.') + '</p>'); return; }
    filas.forEach(function (t) {
        $l.append($('<label class="cp-plan-tk">').append(
            $('<input type="radio" name="tick_id">').val(t.tick_id).prop('checked', String(t.tick_id) === elegido),
            $('<span class="cp-plan-tk-info">').append(
                $('<strong>').text(Copeba.folio(t.tick_id) + ' · ' + t.tick_titulo),
                $('<small>').text([t.sede_nom, t.solicitante, Copeba.fecha(t.fech_crea)].filter(Boolean).join(' · '))),
            $('<span class="cp-plan-tk-chips">').append(
                $('<span class="cp-prioridad">').addClass('p' + t.prio_id).text(t.prio_nom),
                t.usu_asig ? $('<span class="cp-chip">').text('Tuyo') : $('<span class="cp-estado pendiente">').text('Sin técnico'))
        ));
    });
}

function agregarTicket(e) {
    e.preventDefault();
    var tick = $('#tk_lista input:checked').val();
    if (!tick) { Copeba.aviso('Elige el ticket.'); return; }
    var dia = $('#tk_dia').val();
    if (dia && (dia < periodo.inicio || dia > periodo.fin)) { Copeba.aviso('El día debe estar dentro del periodo (' + textoPeriodo(periodo) + ').'); return; }
    Copeba.enviar(URL_PLAN + 'agregar_ticket', { tipo: tipo, fecha: periodo.inicio, tick_id: tick, texto: $('#tk_texto').val(), dia: dia },
        'No se pudo agregar el ticket.').done(function (r) {
            Copeba.dialogo('dlg_ticket', false);
            cargar();
            Copeba.exito(r.tomado ? 'Ticket agregado a tu plan y asignado a ti.' : 'Ticket agregado a tu plan.');
        });
}

/* ---------- No cumplido / Posponer ---------- */
function abrirJustificar(a, estado) {
    accionJustificar = { act: a, estado: estado };
    var posponer = estado === 'posponer';
    $('#just_titulo').text(posponer ? 'Posponer actividad' : 'No se cumplió');
    $('#just_actividad').text(a.act_texto);
    $('#just_texto').val('').attr('placeholder', posponer ? 'P. ej. La batería de repuesto no ha llegado' : 'P. ej. Se priorizó una falla en Caja');
    $('#campo_nueva_fecha').prop('hidden', !posponer);
    $('#just_fecha').val('').trigger('cp-fecha');
    $('#just_boton').text(posponer ? 'Posponer' : 'Guardar');
    Copeba.dialogo('dlg_justificar');
}

function guardarJustificacion(e) {
    e.preventDefault();
    var j = accionJustificar, texto = $.trim($('#just_texto').val());
    if (!j) { return; }
    if (!texto) { Copeba.aviso('Escribe la justificación.'); return; }
    if (j.estado === 'posponer') {
        var nueva = $('#just_fecha').val();
        if (!nueva) { Copeba.aviso('Elige la nueva fecha.'); return; }
        if (nueva < hoyIso()) { Copeba.aviso('La nueva fecha no puede ser anterior a hoy.'); return; }
        Copeba.enviar(URL_PLAN + 'posponer', { act_id: j.act.act_id, justificacion: texto, nueva_fecha: nueva }, 'No se pudo posponer.')
            .done(function (r) {
                Copeba.dialogo('dlg_justificar', false);
                cargar();
                Copeba.exito('Pospuesta al ' + Copeba.fecha(nueva) + ' (' + textoPeriodo(r.destino).toLowerCase() + ').');
            });
    } else {
        Copeba.enviar(URL_PLAN + 'marcar', { act_id: j.act.act_id, estado: 'no_cumplido', justificacion: texto }, 'No se pudo guardar.')
            .done(function () { Copeba.dialogo('dlg_justificar', false); cargar(); });
    }
}

/* ---------- Mi equipo (jefatura) ---------- */
function crearListaEquipo() {
    $('#lista_equipo').prop('hidden', false);
    listaEquipo = Copeba.listado('#lista_equipo', {
        id: 'plan_equipo',
        titulo: 'Planes de mi equipo',
        descripcion: 'Quién entregó su plan en este periodo y cómo va.',
        icono: ICONO,
        pestanas: [
            { texto: 'Todos', filtro: null },
            { texto: 'Sin entregar', filtro: function (x) { return x.estado === 'sin_plan' || x.estado === 'borrador'; } },
            { texto: 'Por revisar', filtro: function (x) { return x.estado === 'entregado'; } },
            { texto: 'Revisados', filtro: function (x) { return x.estado === 'revisado'; } }
        ],
        placeholder: 'Buscar por nombre, área o sede…',
        buscar: function (x) { return [x.nombre, x.area, x.sede, x.puesto].join(' '); },
        columnas: [
            { titulo: 'Persona', render: function (x) {
                return $('<span>').append($('<strong class="cp-tarea-nombre">').text(x.nombre), $('<small class="cp-suave cp-bloque">').text(x.puesto || ''));
            } },
            { titulo: 'Área', clase: 'cp-suave', render: function (x) { return (x.area || 'Sin área') + (x.sede ? ' · ' + x.sede : ''); } },
            { titulo: 'Plan', orden: function (x) { return ['sin_plan', 'borrador', 'entregado', 'revisado'].indexOf(x.estado); }, render: function (x) {
                var $s = $('<span class="cp-tarea-acciones cp-plan-chips">').append(chip(ESTADO_PLAN, x.estado));
                if (x.tarde) { $s.append($('<span class="cp-prioridad vencido">').text(x.estado === 'entregado' || x.estado === 'revisado' ? 'Tarde' : 'Atrasado')); }
                return $s;
            } },
            { titulo: 'Avance', orden: function (x) { return x.total ? x.hechas / x.total : -1; }, render: function (x) {
                if (!x.total) { return $('<span class="cp-suave">').text('—'); }
                var $b = $('<span class="cp-tarea-avance">');
                $b.append($('<span class="cp-tarea-avance-fila">').append(
                    $('<span class="cp-tarea-barra">').append($('<i>').css('width', (x.hechas / x.total * 100) + '%')),
                    $('<span>').attr('title', x.hechas + ' de ' + x.total + ' actividades hechas').text(x.hechas + '/' + x.total)));
                var extra = [];
                if (x.no_cumplidas) { extra.push(x.no_cumplidas + ' no cumplidas'); }
                if (x.pospuestas) { extra.push(x.pospuestas + ' pospuestas'); }
                if (extra.length) { $b.append($('<small>').text(extra.join(' · '))); }
                return $b;
            } },
            { titulo: '', clase: 'cp-der', render: function (x) {
                if (!x.plan_id) { return ''; }
                return $('<button type="button" class="cp-ver">').attr({ 'data-plan': x.plan_id, title: 'Ver plan', 'aria-label': 'Ver plan de ' + x.nombre }).html(OJO);
            } }
        ],
        vacio: 'Nadie en esta vista.',
        total: 'Personas'
    });
}

function cargarEquipo() {
    if (!listaEquipo) { crearListaEquipo(); }
    listaEquipo.cargando();
    $.post(URL_PLAN + 'equipo', { tipo: tipo, fecha: periodo.inicio }).done(function (r) {
        listaEquipo.datos((Copeba.leer(r) || {}).personas || []);
    });
}

function verPlan(plan_id) {
    $.post(URL_PLAN + 'ver_plan', { plan_id: plan_id }).done(function (r) {
        var d = Copeba.leer(r) || {};
        if (!d.plan) { Copeba.aviso(d.message || 'No se pudo abrir el plan.'); return; }
        planViendo = d.plan;
        var p = d.plan;
        $('#ver_titulo').text(p.persona + ' · ' + textoPeriodo(d.periodo));
        $('#ver_meta').text(p.plan_estado === 'borrador' ? 'Todavía no lo entrega (borrador).'
            : 'Entregado el ' + Copeba.fecha(p.fech_entrega) + (p.tarde ? ' (tarde)' : '') + ' · ' + resumen(p.actividades)
              + (p.plan_estado === 'revisado' ? ' · revisado por ' + p.revisado_por : ''));
        var $l = $('#ver_actividades').empty();
        if (!p.actividades.length) { $l.append($('<li class="cp-vacio">').text('Sin actividades.')); }
        p.actividades.forEach(function (a) { $l.append(filaActividad(a, p.plan_estado, false)); });
        var revisable = d.puede_revisar && p.plan_estado !== 'borrador';
        $('#campo_comentario').prop('hidden', !revisable);
        $('#ver_comentario').val(p.plan_comentario || '');
        $('#btn_pdf_ver').attr('href', 'imprimir.php?plan=' + encodeURIComponent(p.plan_id));
        $('#btn_revisar').prop('hidden', !revisable).text(p.plan_estado === 'revisado' ? 'Actualizar comentario' : 'Marcar revisado');
        Copeba.dialogo('dlg_plan');
    }).fail(function (x) { Copeba.aviso((Copeba.leer(x.responseText) || {}).message || 'No se pudo abrir el plan.'); });
}

function revisar() {
    if (!planViendo) { return; }
    Copeba.enviar(URL_PLAN + 'revisar', { plan_id: planViendo.plan_id, comentario: $('#ver_comentario').val() }, 'No se pudo revisar.')
        .done(function () { Copeba.dialogo('dlg_plan', false); cargarEquipo(); Copeba.exito('Plan revisado.'); });
}
