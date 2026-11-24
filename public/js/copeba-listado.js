/* ==========================================================================
   Copeba.listado: tabla con encabezado, pestañas de filtro, buscador,
   exportar a CSV, total, paginación y "Mostrar por página".
   Todo en el navegador (las listas del sistema son de cientos de filas).

   var lista = Copeba.listado('#contenedor', {
       id: 'tickets',                       // para recordar "por página" en este navegador
       titulo: 'Tickets', descripcion: '...', icono: '<svg..>',
       boton: { texto: 'Nuevo ticket', accion: fn } | null,
       pestanas: [{ texto: 'Todos', filtro: null }, { texto: 'Abiertos', filtro: fn(fila) }],
       buscar: fn(fila) -> texto donde buscar,
       placeholder: 'Buscar…',
       columnas: [{ titulo, clase, render: fn(fila) -> texto | nodo jQuery }],
       vacio: 'No hay registros.',
       exportar: { archivo: 'tickets', campos: [{ titulo, valor: fn(fila) }] },
       total: 'Total de tickets'
   });
   lista.datos(arreglo);   lista.cargando();
   ========================================================================== */
(function ($, Copeba) {
    'use strict';

    var POR_PAGINA = [10, 25, 50, 100];
    var LUPA = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
    var BAJAR = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>';
    var MAS = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';

    function leerPreferencia(id, def) {
        try { var v = Number(localStorage.getItem('cp_por_pagina_' + id)); return POR_PAGINA.indexOf(v) !== -1 ? v : def; }
        catch (e) { return def; }
    }
    function guardarPreferencia(id, v) {
        try { localStorage.setItem('cp_por_pagina_' + id, String(v)); } catch (e) { /* sin almacenamiento: no pasa nada */ }
    }

    function sinAcentos(t) {
        return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    Copeba.listado = function (contenedor, o) {
        var filas = [], visibles = [], pagina = 1, pestana = 0, cargado = false;
        var porPagina = leerPreferencia(o.id || 'general', o.porPagina || 25);
        var $c = $(contenedor).addClass('cp-tarjeta cp-listado').empty();

        /* ---- Encabezado ---- */
        var $cab = $('<div class="cp-listado-cab">').append(
            $('<div class="cp-listado-titulo">').append(
                o.icono ? $('<span class="cp-listado-ico">').html(o.icono) : null,
                $('<div>').append($('<h2>').text(o.titulo || ''), o.descripcion ? $('<p>').text(o.descripcion) : null)
            )
        );
        if (o.boton) {
            $cab.append($('<button type="button" class="cp-btn cp-btn-primario">').html(MAS).append(' ', $('<span>').text(o.boton.texto)).on('click', o.boton.accion));
        }

        /* ---- Barra: pestañas + buscador + exportar ---- */
        var $pestanas = $('<div class="cp-listado-pestanas" role="tablist">');
        $.each(o.pestanas || [], function (i, p) {
            $pestanas.append($('<button type="button" role="tab">').text(p.texto).toggleClass('activa', i === 0)
                .attr('aria-selected', i === 0 ? 'true' : 'false').data('i', i));
        });
        var $buscar = $('<input type="search" class="cp-control">').attr({ placeholder: o.placeholder || 'Buscar…', 'aria-label': o.placeholder || 'Buscar' });
        var $barra = $('<div class="cp-listado-barra">').append(
            (o.pestanas && o.pestanas.length) ? $pestanas : $('<span>'),
            $('<div class="cp-listado-herr">').append(
                $('<label class="cp-listado-buscar">').append($('<span class="cp-listado-lupa">').html(LUPA), $buscar),
                o.exportar ? $('<button type="button" class="cp-btn cp-btn-borde" title="Descargar en Excel (CSV)">').html(BAJAR).append(' Exportar').on('click', exportar) : null
            )
        );

        /* ---- Tabla ---- */
        var $thead = $('<tr>');
        $.each(o.columnas, function (_, col) { $thead.append($('<th>').addClass(col.clase || '').text(col.titulo || '')); });
        var $tbody = $('<tbody>');
        var $tabla = $('<div class="cp-tabla-caja">').append($('<table class="cp-tabla">').append($('<thead>').append($thead), $tbody));

        /* ---- Pie: total, paginación, por página ---- */
        var $total = $('<span class="cp-listado-total">');
        var $paginas = $('<nav class="cp-paginacion" aria-label="Paginación">');
        var $select = $('<select class="cp-control cp-por-pagina" aria-label="Filas por página">');
        $.each(POR_PAGINA, function (_, n) { $select.append($('<option>').val(n).text(n)); });
        $select.val(String(porPagina));
        var $pie = $('<div class="cp-listado-pie">').append(
            $total, $paginas,
            $('<label class="cp-listado-por">').append($('<span>').text('Mostrar por página'), $select)
        );

        $c.append($cab, $barra, $tabla, $pie);

        /* ---- Eventos ---- */
        $pestanas.on('click', 'button', function () {
            pestana = $(this).data('i');
            $(this).addClass('activa').attr('aria-selected', 'true').siblings().removeClass('activa').attr('aria-selected', 'false');
            pagina = 1; filtrar();
        });
        var espera;
        $buscar.on('input', function () { clearTimeout(espera); espera = setTimeout(function () { pagina = 1; filtrar(); }, 150); });
        $select.on('change', function () {
            porPagina = Number($select.val()); guardarPreferencia(o.id || 'general', porPagina); pagina = 1; pintar();
        });
        $paginas.on('click', 'button[data-p]', function () { pagina = Number($(this).data('p')); pintar(); $c[0].scrollIntoView({ block: 'nearest' }); });

        /* ---- Lógica ---- */
        function filtrar() {
            var q = sinAcentos($.trim($buscar.val()));
            var f = o.pestanas && o.pestanas[pestana] ? o.pestanas[pestana].filtro : null;
            visibles = filas.filter(function (fila) {
                if (f && !f(fila)) { return false; }
                return !q || sinAcentos(o.buscar ? o.buscar(fila) : JSON.stringify(fila)).indexOf(q) !== -1;
            });
            pintar();
        }

        function pintar() {
            var total = visibles.length;
            var paginasTot = Math.max(1, Math.ceil(total / porPagina));
            if (pagina > paginasTot) { pagina = paginasTot; }
            var desde = (pagina - 1) * porPagina;

            $tbody.empty();
            if (!cargado) {
                $tbody.append($('<tr>').append($('<td class="cp-vacio">').attr('colspan', o.columnas.length).text('Cargando…')));
            } else if (!total) {
                $tbody.append($('<tr>').append($('<td class="cp-vacio">').attr('colspan', o.columnas.length)
                    .text($.trim($buscar.val()) ? 'Sin resultados para la búsqueda.' : (o.vacio || 'No hay registros.'))));
            }
            $.each(visibles.slice(desde, desde + porPagina), function (_, fila) {
                var $tr = $('<tr>');
                $.each(o.columnas, function (_, col) {
                    var v = col.render(fila);
                    var $td = $('<td>').addClass(col.clase || '');
                    if (v instanceof $ || (v && v.nodeType)) { $td.append(v); } else { $td.text(v === null || v === undefined ? '' : v); }
                    $tr.append($td);
                });
                $tbody.append($tr);
            });

            $total.text((o.total || 'Total') + ': ' + total + (total !== filas.length ? ' de ' + filas.length : ''));
            pintarPaginas(paginasTot);
        }

        function pintarPaginas(n) {
            $paginas.empty();
            function boton(p, texto, etiqueta, deshabilitado) {
                return $('<button type="button">').attr({ 'data-p': p, 'aria-label': etiqueta || ('Página ' + p) })
                    .text(texto).toggleClass('activa', p === pagina && !etiqueta).prop('disabled', !!deshabilitado);
            }
            $paginas.append(boton(pagina - 1, '‹', 'Anterior', pagina === 1));
            var paginasVer = [];
            for (var p = 1; p <= n; p++) {
                if (p === 1 || p === n || Math.abs(p - pagina) <= 1) { paginasVer.push(p); }
            }
            var anterior = 0;
            $.each(paginasVer, function (_, p) {
                if (p - anterior > 1) { $paginas.append($('<span class="cp-paginacion-sep">').text('…')); }
                $paginas.append(boton(p, String(p)));
                anterior = p;
            });
            $paginas.append(boton(pagina + 1, '›', 'Siguiente', pagina === n));
        }

        /* CSV con BOM para que Excel respete acentos; separador ";" (Excel en español) */
        function exportar() {
            var e = o.exportar, sep = ';';
            function celda(v) { v = String(v === null || v === undefined ? '' : v); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }
            var lineas = [e.campos.map(function (c) { return celda(c.titulo); }).join(sep)];
            $.each(visibles, function (_, fila) { lineas.push(e.campos.map(function (c) { return celda(c.valor(fila)); }).join(sep)); });
            var blob = new Blob(['﻿' + lineas.join('\r\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = (e.archivo || 'listado') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
        }

        pintar();
        return {
            datos: function (arr) { filas = arr || []; cargado = true; filtrar(); },
            cargando: function () { cargado = false; pintar(); },
            filas: function () { return filas; }
        };
    };

    /* ---------- Ojo para ver/ocultar lo que se escribe en campos de contraseña ---------- */
    var OJO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    var OJO_NO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17.9 17.9A10.1 10.1 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 5.1-5.9M9.9 4.2A9.1 9.1 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2"/></svg>';

    Copeba.ojos = function (raiz) {
        $(raiz || document).find('input[type="password"]:not([data-ojo])').each(function () {
            var $i = $(this).attr('data-ojo', '1');
            var $b = $('<button type="button" class="cp-ojo-pass" aria-label="Mostrar contraseña" aria-pressed="false">').html(OJO);
            $i.wrap('<span class="cp-pass"></span>').after($b);
            $b.on('click', function () {
                var ver = $i.attr('type') === 'password';
                $i.attr('type', ver ? 'text' : 'password');
                $b.html(ver ? OJO_NO : OJO).attr({ 'aria-pressed': ver, 'aria-label': ver ? 'Ocultar contraseña' : 'Mostrar contraseña' });
                $i.trigger('focus');
            });
        });
    };
    $(function () { Copeba.ojos(); });
})(jQuery, Copeba);
