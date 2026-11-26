/* Reportes de actividad: un período, una petición, gráficas en HTML/CSS.
   Colores: magnitud = un solo tono; tendencia = azul (Recibidos) + verde (Resueltos),
   validados para daltonismo. Todo texto se inserta con .text(). */
var datos = null;
var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
function numero(n) { return Number(n || 0).toLocaleString('es-GT'); }
function nombreMes(ym) { var p = ym.split('-'); return MESES[Number(p[1]) - 1] + ' ' + p[0].slice(2); }

/* "17.9" horas -> "17.9 h" o "2 d 3 h" */
function duracion(horas) {
    if (horas === null || horas === undefined || horas === '') { return '—'; }
    horas = Number(horas);
    if (horas >= 48) { return Math.floor(horas / 24) + ' d ' + Math.round(horas % 24) + ' h'; }
    return horas.toFixed(1).replace('.0', '') + ' h';
}

function periodo(clave) {
    var hoy = new Date(), d = new Date(hoy.getFullYear(), hoy.getMonth(), 1), h = hoy;
    if (clave === 'anterior') { d = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1); h = new Date(hoy.getFullYear(), hoy.getMonth(), 0); }
    if (clave === '3m') { d = new Date(hoy.getFullYear(), hoy.getMonth() - 2, 1); }
    if (clave === '6m') { d = new Date(hoy.getFullYear(), hoy.getMonth() - 5, 1); }
    if (clave === 'anio') { d = new Date(hoy.getFullYear(), 0, 1); }
    return { desde: iso(d), hasta: iso(h) };
}

$(function () {
    Copeba.tablaOrdenable('.cp-tecnicos table');
    $('#periodos').on('click', 'button', function () {
        $(this).addClass('activa').siblings().removeClass('activa');
        var p = periodo($(this).data('p'));
        $('#desde').val(p.desde).trigger('cp-fecha'); $('#hasta').val(p.hasta).trigger('cp-fecha');
        cargar();
    });
    $('#desde, #hasta').on('change', function () { $('#periodos button').removeClass('activa'); cargar(); });
    $('#btn_imprimir').on('click', function () { window.print(); });
    $('#btn_exportar').on('click', exportar);

    /* Por defecto, los últimos 6 meses: así la tendencia tiene sentido */
    $('#periodos button[data-p="6m"]').addClass('activa').siblings().removeClass('activa');
    var p = periodo('6m');
    $('#desde').val(p.desde).trigger('cp-fecha'); $('#hasta').val(p.hasta).trigger('cp-fecha');
    cargar();
});

function cargar() {
    var desde = $('#desde').val(), hasta = $('#hasta').val();
    if (!desde || !hasta || desde > hasta) { Copeba.aviso('Revisa las fechas del período.'); return; }
    $('#periodo_texto').text('Período: ' + Copeba.fecha(desde) + ' al ' + Copeba.fecha(hasta));
    $('body').addClass('cp-cargando-reporte');
    Copeba.enviar('../../controller/reporte.php?op=resumen', { desde: desde, hasta: hasta }, 'No se pudo cargar el reporte.')
        .done(function (r) {
            datos = r;
            pintarIndicadores(r.indicadores);
            pintarTendencia(r.tendencia);
            pintarBarras('#areas', r.areas, 'Sin área asignada');
            pintarBarras('#sedes', r.sedes);
            pintarBarras('#categorias', r.categorias);
            pintarBarras('#urgencias', r.urgencias);
            pintarTecnicos(r.tecnicos);
        })
        .always(function () { $('body').removeClass('cp-cargando-reporte'); });
}

/* ---------- Indicadores ---------- */
function pintarIndicadores(k) {
    var resueltos = Number(k.resueltos), pct = resueltos ? Math.round(Number(k.en_sla) * 100 / resueltos) : null;
    var tiles = [
        ['Recibidos', numero(k.recibidos), 'Tickets creados en el período'],
        ['Resueltos', numero(k.resueltos), 'Cerrados en el período'],
        ['Dentro del tiempo objetivo', pct === null ? '—' : pct + ' %', numero(k.en_sla) + ' de ' + numero(resueltos) + ' resueltos a tiempo'],
        ['Tiempo promedio de resolución', duracion(k.horas_promedio), 'Desde que se reporta hasta que se cierra'],
        ['Pendientes hoy', numero(k.pendientes), numero(k.vencidos) + ' fuera del tiempo objetivo']
    ];
    var $k = $('#indicadores').empty();
    $.each(tiles, function (_, t) {
        $k.append($('<article class="cp-tarjeta cp-kpi">').append(
            $('<div class="cp-kpi-cab">').append($('<span>').text(t[0])),
            $('<div class="cp-kpi-num cp-kpi-grande">').text(t[1]),
            $('<div class="cp-kpi-nota">').text(t[2])
        ));
    });
}

/* ---------- Tendencia: columnas agrupadas con tooltip ---------- */
function pintarTendencia(meses) {
    var $c = $('#tendencia').empty(), $t = $('#tendencia_tabla').empty();
    var max = 1;
    $.each(meses, function (_, m) { max = Math.max(max, m.recibidos, m.resueltos); });
    if (!meses.length) { $c.append($('<p class="cp-vacio">').text('Sin datos en el período.')); return; }

    $.each(meses, function (_, m) {
        var texto = nombreMes(m.mes) + ': ' + m.recibidos + ' recibidos · ' + m.resueltos + ' resueltos';
        $c.append($('<div class="cp-col-grupo" tabindex="0">').attr({ 'aria-label': texto, 'data-tip': texto }).append(
            $('<div class="cp-col-barras">').append(
                $('<span class="cp-col s1">').css('height', (m.recibidos * 100 / max) + '%'),
                $('<span class="cp-col s2">').css('height', (m.resueltos * 100 / max) + '%')
            ),
            $('<span class="cp-col-etq">').text(nombreMes(m.mes))
        ));
    });
    /* Escala: solo el máximo, como referencia recesiva */
    $c.prepend($('<span class="cp-col-max">').text(numero(max)));

    $t.append($('<thead>').append($('<tr>').append($('<th>').text('Mes'), $('<th class="cp-der">').text('Recibidos'), $('<th class="cp-der">').text('Resueltos'))));
    var $b = $('<tbody>');
    $.each(meses, function (_, m) {
        $b.append($('<tr>').append($('<td>').text(nombreMes(m.mes)), $('<td class="cp-der cp-mono">').text(m.recibidos), $('<td class="cp-der cp-mono">').text(m.resueltos)));
    });
    $t.append($b);
}

/* ---------- Barras horizontales (un solo tono: magnitud) ---------- */
function pintarBarras(sel, filas, textoNulo) {
    var $c = $(sel).empty();
    filas = (filas || []).filter(function (f) { return Number(f.recibidos) > 0; });
    if (!filas.length) { $c.append($('<p class="cp-vacio">').text('Sin tickets en el período.')); return; }
    var max = Math.max.apply(null, filas.map(function (f) { return Number(f.recibidos); }));
    /* Más de 8: el resto se agrupa en "Otras" */
    if (filas.length > 8) {
        var resto = filas.slice(7).reduce(function (s, f) { return s + Number(f.recibidos); }, 0);
        filas = filas.slice(0, 7).concat([{ nombre: 'Otras', recibidos: resto }]);
    }
    $.each(filas, function (_, f) {
        var nombre = f.nombre === 'Sin dato' ? (textoNulo || 'Sin dato') : f.nombre;
        var detalle = (f.resueltos !== undefined) ? numero(f.resueltos) + ' resueltos · ' + numero(f.pendientes) + ' pendientes' : '';
        var tip = nombre + ': ' + numero(f.recibidos) + ' tickets' + (detalle ? ' (' + detalle + ')' : '');
        $c.append($('<div class="cp-barra-h" tabindex="0">').attr({ 'aria-label': tip, 'data-tip': tip }).append(
            $('<span class="cp-barra-h-nombre">').text(nombre),
            $('<span class="cp-barra-h-pista">').append($('<span>').css('width', Math.max(2, Number(f.recibidos) * 100 / max) + '%')),
            $('<span class="cp-barra-h-valor cp-mono">').text(numero(f.recibidos))
        ));
    });
}

/* ---------- Técnicos ---------- */
function pintarTecnicos(filas) {
    var $t = $('#tecnicos').empty();
    if (!filas.length) { $t.append($('<tr>').append($('<td colspan="5" class="cp-vacio">').text('Sin actividad del equipo en el período.'))); return; }
    var max = Math.max.apply(null, filas.map(function (f) { return Number(f.resueltos || 0); })) || 1;
    $.each(filas, function (_, f) {
        $t.append($('<tr>').append(
            $('<td>').append($('<strong>').text(f.nombre)),
            $('<td class="cp-der cp-mono">').attr('data-orden', Number(f.asignados || 0)).text(numero(f.asignados)),
            $('<td>').attr('data-orden', Number(f.resueltos || 0)).append($('<span class="cp-barra-celda">').append(
                $('<span class="cp-barra-h-pista">').append($('<span>').css('width', (Number(f.resueltos || 0) * 100 / max) + '%')),
                $('<span class="cp-mono">').text(numero(f.resueltos)))),
            $('<td class="cp-der cp-mono">').attr('data-orden', Number(f.pendientes || 0)).text(numero(f.pendientes)),
            $('<td class="cp-der cp-mono">').attr('data-orden', f.horas_promedio === null ? '' : Number(f.horas_promedio)).text(duracion(f.horas_promedio))
        ));
    });
}

/* ---------- Tooltip (hover y foco con teclado) ---------- */
$(document).on('mouseenter focus', '[data-tip]', function () {
    var r = this.getBoundingClientRect();
    $('#tooltip').text($(this).attr('data-tip')).prop('hidden', false)
        .css({ left: Math.min(window.innerWidth - 260, Math.max(8, r.left + r.width / 2 - 120)) + 'px', top: (r.top - 44) + 'px' });
}).on('mouseleave blur', '[data-tip]', function () { $('#tooltip').prop('hidden', true); });

/* ---------- Exportar todo el reporte a CSV ---------- */
function exportar() {
    if (!datos) { return; }
    var sep = ';', l = [];
    function celda(v) { v = String(v === null || v === undefined ? '' : v); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }
    function fila(arr) { l.push(arr.map(celda).join(sep)); }
    var k = datos.indicadores;
    fila(['Reporte de actividad', 'Copeba Tickets']); fila(['Desde', $('#desde').val()]); fila(['Hasta', $('#hasta').val()]); fila([]);
    fila(['Recibidos', k.recibidos]); fila(['Resueltos', k.resueltos]); fila(['Resueltos a tiempo', k.en_sla]);
    fila(['Horas promedio de resolución', k.horas_promedio]); fila(['Pendientes hoy', k.pendientes]); fila(['Fuera de tiempo hoy', k.vencidos]); fila([]);
    fila(['Mes', 'Recibidos', 'Resueltos']); $.each(datos.tendencia, function (_, m) { fila([m.mes, m.recibidos, m.resueltos]); }); fila([]);
    $.each([['Área', datos.areas], ['Sede', datos.sedes], ['Categoría', datos.categorias]], function (_, g) {
        fila([g[0], 'Recibidos', 'Resueltos', 'Pendientes']);
        $.each(g[1], function (_, f) { fila([f.nombre, f.recibidos, f.resueltos, f.pendientes]); });
        fila([]);
    });
    fila(['Urgencia', 'Recibidos']); $.each(datos.urgencias, function (_, f) { fila([f.nombre, f.recibidos]); }); fila([]);
    fila(['Técnico', 'Asignados', 'Resueltos', 'Pendientes hoy', 'Horas promedio']);
    $.each(datos.tecnicos, function (_, f) { fila([f.nombre, f.asignados, f.resueltos, f.pendientes, f.horas_promedio]); });
    var blob = new Blob(['﻿' + l.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'reporte_' + $('#desde').val() + '_a_' + $('#hasta').val() + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
}
