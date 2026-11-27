/* Planificación semanal y mensual.
   - Filtro Desde–Hasta: se ven todos mis planes (semanas o meses) de ese rango, uno
     debajo del otro, cada uno con su estado y sus acciones. Se carga todo de una vez.
   - Cada plan: escribir actividades, entregar antes de que empiece el periodo y,
     durante el periodo, marcar cada una: hecho, no cumplido o pospuesto (con justificación).
   - Mi equipo (solo jefatura): quién entregó, avance y revisión, en el mismo rango.
   Todo texto de la base se inserta con .text(). */
var URL_PLAN = '../../controller/plan.php?op=';
var tipo = 'semana', desde = null, hasta = null;
var bloques = [], hoyServidor = null, esSoporte = false;
var destino = null;               /* periodo al que van los diálogos "Desde un ticket / una nota" */
var listaEquipo = null, planViendo = null, cargandoPlanes = false;
var accionJustificar = null;      /* { act, estado: 'no_cumplido' | 'posponer' } */

var DIAS_CORTOS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
var OJO = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
var ICONO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h2M14 14h2M8 18h2"/></svg>';

var ESTADO_ACT = {
    pendiente:   { texto: 'Pendiente',    clase: 'pendiente' },
    hecho:       { texto: 'Hecho',        clase: 'abierto' },
    no_cumplido: { texto: 'No cumplido',  clase: 'cerrado' },
    pospuesto:   { texto: 'Pospuesto',    clase: 'neutro' }
};
var ESTADO_PLAN = {
    sin_plan:  { texto: 'Sin plan',  clase: 'cerrado' },
    borrador:  { texto: 'Borrador',  clase: 'pendiente' },
    entregado: { texto: 'Entregado', clase: 'proceso' },
    revisado:  { texto: 'Revisado',  clase: 'revisada' }
};

/* ---------- Fechas ---------- */
function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
function hoyIso() { return iso(new Date()); }
function deIso(v) { var p = String(v).substr(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
function sumarDias(v, n) { var d = deIso(v); d.setDate(d.getDate() + n); return iso(d); }
function diaCorto(v) { var d = deIso(v); return DIAS_CORTOS[d.getDay()] + ' ' + d.getDate(); }
function lunes(v) { var d = deIso(v); var n = d.getDay() === 0 ? 6 : d.getDay() - 1; d.setDate(d.getDate() - n); return iso(d); }
function inicioMes(v, mas) { var d = deIso(v); return iso(new Date(d.getFullYear(), d.getMonth() + (mas || 0), 1)); }
function finMes(v, mas) { var d = deIso(v); return iso(new Date(d.getFullYear(), d.getMonth() + (mas || 0) + 1, 0)); }
function textoPeriodo(p) {
    if (!p) { return ''; }
    if (p.tipo === 'mes') { var d = deIso(p.inicio); return MESES[d.getMonth()].replace(/^./, function (c) { return c.toUpperCase(); }) + ' ' + d.getFullYear(); }
    return 'Semana del ' + Copeba.fecha(p.inicio).substr(0, 5) + ' al ' + Copeba.fecha(p.fin);
}
function chip(mapa, clave) {
    var e = mapa[clave] || mapa[Object.keys(mapa)[0]];
    return $('<span class="cp-estado">').addClass(e.clase).text(e.texto);
}

/* Atajos del filtro según el tipo de plan: [texto, desde, hasta] */
function atajos() {
    var h = hoyIso(), l = lunes(h);
    if (tipo === 'mes') {
        return [
            ['Mes pasado', inicioMes(h, -1), finMes(h, -1)],
            ['Este mes', inicioMes(h), finMes(h)],
            ['Próximo mes', inicioMes(h, 1), finMes(h, 1)],
            ['Próximos 3 meses', inicioMes(h), finMes(h, 2)]
        ];
    }
    return [
        ['Semana pasada', sumarDias(l, -7), sumarDias(l, -1)],
        ['Esta semana', l, sumarDias(l, 6)],
        ['Próxima semana', sumarDias(l, 7), sumarDias(l, 13)],
        ['Próximas 4 semanas', l, sumarDias(l, 27)]
    ];
}
/* Rango inicial: esta semana (lunes a domingo) o este mes */
function rangoInicial() {
    var h = hoyIso(), l = lunes(h);
    return tipo === 'mes' ? [inicioMes(h), finMes(h)] : [l, sumarDias(l, 6)];
}
/* Al elegir "Desde", "Hasta" se pone sola una semana después (o al fin de ese mes) */
function hastaSugerido(d) { return tipo === 'mes' ? finMes(d) : sumarDias(d, 6); }
function pintarAtajos() {
    var $a = $('#atajos').empty();
    atajos().forEach(function (x) {
        $a.append($('<button type="button" class="cp-chip-filtro">').text(x[0])
            .toggleClass('activa', x[1] === desde && x[2] === hasta)
            .on('click', function () { ponerRango(x[1], x[2]); cargar(); }));
    });
}
function ponerRango(d, h) {
    desde = d; hasta = h;
    $('#f_desde').val(d).trigger('cp-fecha');
    $('#f_hasta').val(h).trigger('cp-fecha');
}

$(function () {
    $('.cp-plan-tipo').on('click', 'button', function () {
        tipo = $(this).data('tipo');
        $(this).addClass('activa').attr('aria-selected', 'true').siblings().removeClass('activa').attr('aria-selected', 'false');
        var r = rangoInicial(); ponerRango(r[0], r[1]);
        cargar();
    });
    $('#f_desde').on('change', function () {
        var d = $(this).val();
        if (d) { $('#f_hasta').val(hastaSugerido(d)).trigger('cp-fecha'); }
    });
    $('#filtro_form').on('submit', function (e) {
        e.preventDefault();
        var d = $('#f_desde').val(), h = $('#f_hasta').val();
        if (!d || !h) { Copeba.aviso('Elige las dos fechas: Desde y Hasta.'); return; }
        if (h < d) { Copeba.aviso('"Hasta" no puede ser antes que "Desde".'); return; }
        desde = d; hasta = h;
        cargar();
    });

    /* Acciones dentro de cada plan (los bloques se dibujan de nuevo en cada carga) */
    $('#planes')
        .on('click', '[data-accion]', accionActividad)
        .on('submit', 'form[data-agregar]', agregar)
        .on('click', '[data-entregar]', function () { entregar(bloquePorInicio($(this).attr('data-entregar'))); })
        .on('click', '[data-desde-ticket]', function () { abrirTickets(bloquePorInicio($(this).attr('data-desde-ticket'))); })
        .on('click', '[data-desde-nota]', function () { abrirNotas(null, bloquePorInicio($(this).attr('data-desde-nota'))); });

    $('#just_form').on('submit', guardarJustificacion);
    $('#btn_revisar').on('click', revisar);
    $('dialog').on('click', '[data-cerrar]', function () { this.closest('dialog').close(); });
    $('#lista_equipo').on('click', '[data-plan]', function () { verPlan(Number($(this).attr('data-plan'))); });
    $('#ticket_form').on('submit', agregarTicket);
    $('#tk_buscar').on('input', filtrarTickets);
    $('#nota_form').on('submit', agregarNota);
    $('#nt_buscar').on('input', filtrarNotas);

    var r = rangoInicial(); ponerRango(r[0], r[1]);
    cargar();
    if ($('#lista_notas').length) { cargarNotas(); }
});

/* ---------- Mis planes del rango ---------- */
function cargar() {
    if (cargandoPlanes) { return; }
    cargandoPlanes = true;
    pintarAtajos();
    var $b = $('#btn_filtrar').prop('disabled', true).text('Cargando…');
    var $planes = $('#planes').addClass('cp-cargando');
    if (!bloques.length) { $planes.html('<p class="cp-vacio">Cargando planes…</p>'); }
    $.post(URL_PLAN + 'rango', { tipo: tipo, desde: desde, hasta: hasta })
        .always(function () { cargandoPlanes = false; $b.prop('disabled', false).text('Ver'); $planes.removeClass('cp-cargando'); })
        .done(function (r) {
            var d = Copeba.leer(r) || {};
            if (d.status === 'error') { Copeba.aviso(d.message); return; }
            bloques = d.bloques || [];
            hoyServidor = d.hoy || hoyIso();
            esSoporte = !!d.soporte;
            pintarPlanes();
            if (d.dirige) { cargarEquipo(); }
        })
        .fail(function () { $planes.html('<p class="cp-vacio">No se pudieron cargar los planes. Intenta de nuevo.</p>'); });
}

function bloquePorInicio(inicio) {
    return bloques.filter(function (b) { return b.periodo.inicio === inicio; })[0] || null;
}
/* Bloque del periodo de hoy (o el primero del rango): destino por defecto */
function bloqueDeHoy() {
    var h = hoyServidor || hoyIso();
    return bloques.filter(function (b) { return b.periodo.inicio <= h && h <= b.periodo.fin; })[0] || bloques[0] || null;
}

function pintarPlanes() {
    var $planes = $('#planes').empty();
    if (!bloques.length) { $planes.append($('<p class="cp-vacio">').text('No hay periodos en ese rango.')); return; }
    bloques.forEach(function (b) { $planes.append(tarjetaPlan(b)); });
    $planes.find('input[type="date"]').each(function () { Copeba.calendario(this); });
}

function tarjetaPlan(b) {
    var p = b.plan, periodo = b.periodo, estado = p ? p.plan_estado : 'sin_plan';
    var hoy = hoyServidor || hoyIso();
    var actual = periodo.inicio <= hoy && hoy <= periodo.fin;
    var acts = p ? p.actividades : [];
    var $t = $('<section class="cp-tarjeta cp-plan">').attr('data-inicio', periodo.inicio).toggleClass('cp-plan-es-actual', actual);

    /* Encabezado: periodo, plazo / entrega, estado y botones */
    var $titulo = $('<h2>').text(textoPeriodo(periodo));
    if (actual) { $titulo.append(' ', $('<span class="cp-chip cp-plan-hoy">').text(periodo.tipo === 'mes' ? 'Este mes' : 'Esta semana')); }
    var $plazo = $('<p>');
    if (!p || estado === 'borrador') {
        $plazo.text('Entrégalo a más tardar el ' + Copeba.fecha(periodo.plazo) + '.');
        if (hoy > periodo.plazo && periodo.fin >= hoy) { $plazo.addClass('cp-plan-atrasado').text('El plazo de entrega era el ' + Copeba.fecha(periodo.plazo) + ': entrégalo cuanto antes.'); }
    } else {
        $plazo.text('Entregado el ' + Copeba.fecha(p.fech_entrega) + (estado === 'revisado' ? ' · revisado por ' + p.revisado_por : ''));
    }
    var $der = $('<div class="cp-plan-cab-der">').append(chip(ESTADO_PLAN, estado));
    if (p && p.tarde) { $der.append($('<span class="cp-prioridad vencido">').text('Entregado tarde')); }
    if (p) { $der.append(botonPdf(p.plan_id)); }
    if (estado === 'borrador' && acts.length) {
        $der.append($('<button type="button" class="cp-btn cp-btn-primario cp-btn-chico">').attr('data-entregar', periodo.inicio).text('Entregar plan'));
    }
    $t.append($('<div class="cp-plan-cab">').append($('<div>').append($titulo, $plazo), $der));

    if (p && p.plan_comentario) {
        $t.append($('<p class="cp-tarea-nota">').text('Comentario de ' + (p.revisado_por || 'tu jefatura') + ': ' + p.plan_comentario));
    }

    var $l = $('<ul class="cp-plan-lista">');
    if (!acts.length) { $l.append($('<li class="cp-vacio">').text('Todavía no hay actividades en este periodo.')); }
    acts.forEach(function (a) { $l.append(filaActividad(a, estado, true)); });
    $t.append($l);

    /* Agregar: siempre que no esté revisado; después de entregar queda como "agregada después" */
    if (estado !== 'revisado') {
        var $form = $('<form class="cp-plan-nueva" novalidate>').attr('data-agregar', periodo.inicio).append(
            $('<input type="text" class="cp-control" maxlength="300" autocomplete="off" aria-label="Nueva actividad">')
                .attr('placeholder', estado === 'entregado'
                    ? 'Imprevisto: se marcará como agregado después de entregar'
                    : 'Nueva actividad, p. ej. Cambio de batería laptop de Caja Soloma'),
            $('<input type="date" aria-label="Día (opcional)">'),
            $('<button type="submit" class="cp-btn cp-btn-borde">').text('Agregar'));
        if (esSoporte) {
            $form.append(
                $('<button type="button" class="cp-btn cp-btn-borde">').attr('data-desde-ticket', periodo.inicio).text('Desde un ticket'),
                $('<button type="button" class="cp-btn cp-btn-borde">').attr('data-desde-nota', periodo.inicio).text('Desde una nota'));
        }
        $t.append($form);
    }
    if (estado !== 'sin_plan' && estado !== 'borrador') {
        $t.append($('<div class="cp-plan-pie">').append($('<span class="cp-plan-resumen">').text(resumen(acts))));
    }
    return $t;
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
        /* Ticket del que viene: botón al detalle y su estado actual */
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

/* Busca la actividad en todos los planes del rango */
function actividadPorId(id) {
    for (var i = 0; i < bloques.length; i++) {
        var acts = bloques[i].plan ? bloques[i].plan.actividades : [];
        for (var j = 0; j < acts.length; j++) { if (Number(acts[j].act_id) === id) { return acts[j]; } }
    }
    return null;
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
    var $f = $(this), b = bloquePorInicio($f.attr('data-agregar'));
    var $texto = $f.find('input[type="text"]'), $dia = $f.find('input[type="date"]');
    var texto = $.trim($texto.val());
    if (!b) { return; }
    if (!texto) { Copeba.aviso('Escribe la actividad.'); return; }
    var $btn = $f.find('button[type="submit"]');
    if ($btn.prop('disabled')) { return; }
    $btn.prop('disabled', true);
    Copeba.enviar(URL_PLAN + 'agregar', { tipo: b.periodo.tipo, fecha: b.periodo.inicio, texto: texto, dia: $dia.val() }, 'No se pudo agregar.')
        .always(function () { $btn.prop('disabled', false); })
        .done(function (r) { avisoOtroPeriodo(r, b.periodo, 'Actividad agregada.'); cargar(); });
}

function entregar(b) {
    if (!b) { return; }
    Copeba.confirmar('¿Entregar el plan de ' + textoPeriodo(b.periodo).toLowerCase() + '? Después ya no podrás cambiar ni quitar actividades, solo marcar tu avance.', function () {
        Copeba.enviar(URL_PLAN + 'entregar', { tipo: b.periodo.tipo, fecha: b.periodo.inicio }, 'No se pudo entregar.').done(function (r) {
            cargar();
            Copeba.exito(r.tarde ? 'Plan entregado (fuera del plazo).' : 'Plan entregado.');
        });
    });
}

/* Si la actividad se fue al plan de otro periodo (día elegido fuera del suyo), avisar dónde quedó */
function avisoOtroPeriodo(r, base, mensaje) {
    if (r && r.periodo && base && r.periodo.inicio !== base.inicio) {
        var fuera = !bloquePorInicio(r.periodo.inicio);
        Copeba.exito(mensaje + ' Quedó en tu plan de ' + textoPeriodo(r.periodo).toLowerCase()
            + (fuera ? ' (fuera del rango que estás viendo).' : '.'));
        return true;
    }
    return false;
}

/* Revisado = ya no se le agregan actividades */
function destinoAbierto(b) {
    if (!b) { Copeba.aviso('Elige un rango con al menos un periodo.'); return false; }
    if (b.plan && b.plan.plan_estado === 'revisado') { Copeba.aviso('El plan de ' + textoPeriodo(b.periodo).toLowerCase() + ' ya fue revisado: elige otro periodo o un día de otra semana.'); }
    return !(b.plan && b.plan.plan_estado === 'revisado');
}

/* ---------- Notas internas de TI (solo quien atiende tickets) ----------
   Avisos escritos en los tickets ("se acabó la pasta térmica", "no hay teclados")
   para que alguien de TI los tome en cuenta al hacer su plan. Se pueden convertir en
   actividad: "Nota interna 004 · Salida por suministros". */
var notasInternas = [];
function folioNota(id) { var t = String(id); return t.length < 3 ? ('000' + t).slice(-3) : t; }
function sinAcentosNota(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }

function cargarNotas() {
    var lista = Copeba.listado('#lista_notas', {
        id: 'plan_notas',
        titulo: 'Notas internas de TI',
        descripcion: 'Lo que el equipo anotó en los tickets (últimos 60 días): tenlo en cuenta al planificar.',
        icono: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
        pestanas: [
            { texto: 'Tickets abiertos', filtro: function (n) { return n.tick_estado !== 'Cerrado'; } },
            { texto: 'Todas', filtro: null }
        ],
        placeholder: 'Buscar en las notas, el ticket o quién la escribió…',
        buscar: function (n) { return [folioNota(n.tickd_id), n.nota, Copeba.folio(n.tick_id), n.tick_titulo, n.autor].join(' '); },
        columnas: [
            { titulo: 'N.º', clase: 'cp-mono', orden: function (n) { return Number(n.tickd_id); }, render: function (n) { return folioNota(n.tickd_id); } },
            { titulo: 'Fecha', clase: 'cp-mono', orden: function (n) { return n.fech_crea; }, render: function (n) { return Copeba.fecha(n.fech_crea); } },
            { titulo: 'Nota', render: function (n) { return $('<span class="cp-plan-nota-texto">').text(n.nota); } },
            { titulo: 'Ticket', render: function (n) {
                return $('<span>').append(
                    $('<strong class="cp-tarea-nombre">').text(Copeba.folio(n.tick_id) + ' · ' + n.tick_titulo),
                    ' ', Copeba.estado(n.tick_estado));
            } },
            { titulo: 'Escribió', render: function (n) { return n.autor; } },
            { titulo: '', clase: 'cp-der cp-acciones-fila', orden: false, render: function (n) {
                return $('<span>').append(
                    $('<button type="button" class="cp-btn cp-btn-borde cp-btn-chico">').text('Planificar')
                        .attr('title', 'Agregar a mi plan a partir de esta nota')
                        .on('click', function () { abrirNotas(n.tickd_id, bloqueDeHoy()); }),
                    ' ',
                    $('<a class="cp-ver">').attr({ href: '../DetalleTicket/?ID=' + encodeURIComponent(n.tick_id), title: 'Ver ticket', 'aria-label': 'Ver ticket ' + Copeba.folio(n.tick_id) }).html(OJO));
            } }
        ],
        vacio: 'No hay notas internas en esta vista.',
        total: 'Total de notas'
    });
    lista.cargando();
    $.post(URL_PLAN + 'notas').done(function (r) {
        notasInternas = Copeba.leer(r) || [];
        lista.datos(notasInternas);
    });
}

/* Diálogo "Planificar desde una nota" (opcionalmente con una nota ya elegida) */
function abrirNotas(elegida, bloque) {
    if (!destinoAbierto(bloque)) { return; }
    destino = bloque.periodo;
    $('#nota_form')[0].reset();
    $('#nt_titulo').text('Planificar desde una nota · ' + textoPeriodo(destino));
    $('#nt_dia').val('').trigger('cp-fecha');
    $('#nt_lista').empty().data('elegida', elegida ? String(elegida) : '');
    filtrarNotas();
    Copeba.dialogo('dlg_nota');
}

function filtrarNotas() {
    var q = sinAcentosNota($.trim($('#nt_buscar').val()));
    var $l = $('#nt_lista').removeData('cp-pagina');
    var elegida = $l.find('input:checked').val() || $l.data('elegida') || '';
    $l.empty();
    var filas = notasInternas.filter(function (n) {
        if (n.tick_estado === 'Cerrado' && String(n.tickd_id) !== elegida) { return false; }
        return !q || sinAcentosNota([folioNota(n.tickd_id), n.nota, Copeba.folio(n.tick_id), n.tick_titulo, n.autor].join(' ')).indexOf(q) !== -1;
    });
    if (!filas.length) { $l.html('<p class="cp-suave">' + (notasInternas.length ? 'Ninguna nota coincide.' : 'No hay notas internas en tickets abiertos.') + '</p>'); return; }
    filas.forEach(function (n) {
        $l.append($('<label class="cp-plan-tk">').append(
            $('<input type="radio" name="tickd_id">').val(n.tickd_id).prop('checked', String(n.tickd_id) === elegida),
            $('<span class="cp-plan-tk-info">').append(
                $('<strong>').text('Nota interna ' + folioNota(n.tickd_id) + ' · ' + String(n.nota).split('\n')[0].slice(0, 120)),
                $('<small>').text([Copeba.folio(n.tick_id) + ' ' + n.tick_titulo, n.autor, Copeba.fecha(n.fech_crea)].join(' · '))),
            $('<span class="cp-plan-tk-chips">').append(Copeba.estado(n.tick_estado))
        ));
    });
    Copeba.paginar($l, 5);
}

function agregarNota(e) {
    e.preventDefault();
    var $b = $('#nt_enviar');
    if ($b.prop('disabled') || !destino) { return; }
    var nota = $('#nt_lista input:checked').val();
    if (!nota) { Copeba.aviso('Elige la nota.'); return; }
    if (!$.trim($('#nt_texto').val())) { Copeba.aviso('Escribe la actividad, p. ej. Salida por suministros.'); return; }
    var base = destino;
    $b.prop('disabled', true);
    Copeba.enviar(URL_PLAN + 'agregar_nota', { tipo: base.tipo, fecha: base.inicio, tickd_id: nota, texto: $('#nt_texto').val(), dia: $('#nt_dia').val() },
        'No se pudo agregar la actividad.')
        .always(function () { $b.prop('disabled', false); })
        .done(function (r) {
            Copeba.dialogo('dlg_nota', false);
            cargar();
            if (!avisoOtroPeriodo(r, base, 'Nota interna ' + folioNota(nota) + ' planificada.')) {
                Copeba.exito('Actividad agregada a tu plan: Nota interna ' + folioNota(nota) + '.');
            }
        });
}

/* ---------- Traer un ticket al plan ---------- */
var ticketsDisponibles = [];

function abrirTickets(bloque) {
    if (!destinoAbierto(bloque)) { return; }
    destino = bloque.periodo;
    $('#ticket_form')[0].reset();
    $('#tk_titulo').text('Planificar un ticket · ' + textoPeriodo(destino));
    $('#tk_dia').val('').trigger('cp-fecha');
    $('#tk_lista').html('<p class="cp-suave">Cargando tickets…</p>');
    Copeba.dialogo('dlg_ticket');
    $.post(URL_PLAN + 'tickets').done(function (r) {
        ticketsDisponibles = Copeba.leer(r) || [];
        filtrarTickets();
    });
}

function filtrarTickets() {
    var q = sinAcentosNota($.trim($('#tk_buscar').val()));
    var elegido = $('#tk_lista input:checked').val();
    var $l = $('#tk_lista').removeData('cp-pagina').empty();
    var filas = ticketsDisponibles.filter(function (t) {
        return !q || sinAcentosNota([Copeba.folio(t.tick_id), t.tick_titulo, t.sede_nom, t.solicitante].join(' ')).indexOf(q) !== -1;
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
    Copeba.paginar($l, 5);
}

function agregarTicket(e) {
    e.preventDefault();
    if (!destino) { return; }
    var tick = $('#tk_lista input:checked').val();
    if (!tick) { Copeba.aviso('Elige el ticket.'); return; }
    var base = destino;
    Copeba.enviar(URL_PLAN + 'agregar_ticket', { tipo: base.tipo, fecha: base.inicio, tick_id: tick, texto: $('#tk_texto').val(), dia: $('#tk_dia').val() },
        'No se pudo agregar el ticket.').done(function (r) {
            Copeba.dialogo('dlg_ticket', false);
            cargar();
            var msg = r.tomado ? 'Ticket agregado y asignado a ti.' : 'Ticket agregado.';
            if (!avisoOtroPeriodo(r, base, msg)) { Copeba.exito(r.tomado ? 'Ticket agregado a tu plan y asignado a ti.' : 'Ticket agregado a tu plan.'); }
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

/* ---------- Mi equipo (jefatura): una fila por persona y periodo del rango ---------- */
function crearListaEquipo() {
    $('#lista_equipo').prop('hidden', false);
    listaEquipo = Copeba.listado('#lista_equipo', {
        id: 'plan_equipo',
        titulo: 'Planes de mi equipo',
        descripcion: 'Quién entregó su plan en los periodos del rango elegido y cómo va.',
        icono: ICONO,
        pestanas: [
            { texto: 'Todos', filtro: null },
            { texto: 'Sin entregar', filtro: function (x) { return x.estado === 'sin_plan' || x.estado === 'borrador'; } },
            { texto: 'Por revisar', filtro: function (x) { return x.estado === 'entregado'; } },
            { texto: 'Revisados', filtro: function (x) { return x.estado === 'revisado'; } }
        ],
        placeholder: 'Buscar por nombre, área, sede o periodo…',
        buscar: function (x) { return [x.nombre, x.area, x.sede, x.puesto, textoPeriodo(x.periodo)].join(' '); },
        columnas: [
            { titulo: 'Periodo', clase: 'cp-mono', orden: function (x) { return x.periodo.inicio; }, render: function (x) { return textoPeriodo(x.periodo); } },
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
            { titulo: '', clase: 'cp-der', orden: false, render: function (x) {
                if (!x.plan_id) { return ''; }
                return $('<button type="button" class="cp-ver">').attr({ 'data-plan': x.plan_id, title: 'Ver plan', 'aria-label': 'Ver plan de ' + x.nombre }).html(OJO);
            } }
        ],
        vacio: 'Nadie en esta vista.',
        total: 'Planes'
    });
}

function cargarEquipo() {
    if (!listaEquipo) { crearListaEquipo(); }
    listaEquipo.cargando();
    $.post(URL_PLAN + 'equipo_rango', { tipo: tipo, desde: desde, hasta: hasta }).done(function (r) {
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
