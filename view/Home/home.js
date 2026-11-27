/* Inicio: pide un solo resumen al servidor y lo pinta según el rol.
   Todo el texto que viene de la base se inserta con .text() (nunca como HTML). */
(function () {

    function hoy() {
        var d = new Date();
        return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
    }

    function soporte(t) {
        if (t.asignado) { return $('<span>').text(t.asignado); }
        return t.tick_estado !== 'Cerrado'
            ? $('<span class="cp-estado pendiente">').text('Pendiente')
            : $('<span class="cp-suave">').text('—');
    }

    function botonVer(id) {
        return $('<a class="cp-ver">')
            .attr('href', '../DetalleTicket/?ID=' + encodeURIComponent(id))
            .attr('title', 'Ver ticket ' + Copeba.folio(id))
            .attr('aria-label', 'Ver ticket ' + Copeba.folio(id))
            .html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>');
    }

    $(function () { Copeba.tablaOrdenable('.cp-tabla-caja > table.cp-tabla'); });

    function pintarTabla(nombre, filas, textoVacio) {
        var $cuerpo = $('[data-tabla="' + nombre + '"]');
        if (!$cuerpo.length) { return; }
        $cuerpo.empty();
        if (!filas || !filas.length) {
            $cuerpo.append($('<tr>').append($('<td colspan="7" class="cp-vacio">').text(textoVacio)));
            return;
        }
        $.each(filas, function (_, t) {
            $cuerpo.append($('<tr>').append(
                $('<td class="cp-mono">').attr('data-orden', Number(t.tick_id)).text(Copeba.folio(t.tick_id)),
                $('<td>').text(t.tick_titulo),
                $('<td class="cp-suave">').text(t.cat_nom),
                $('<td>').append(Copeba.estado(t.tick_estado, Number(t.vencido) === 1)),
                $('<td>').append(soporte(t)),
                $('<td class="cp-mono">').attr('data-orden', t.fech_crea || '').text(Copeba.fecha(t.fech_crea)),
                $('<td class="cp-acciones-fila">').append(
                    nombre === 'sin_asignar' ? botonResolver(t) : null,
                    botonVer(t.tick_id)
                )
            ));
        });
    }

    /* "Resolver" (solo Informática ve esta tabla): toma el ticket si sigue libre.
       Si otra persona lo tomó primero, el servidor lo rechaza, avisamos y refrescamos. */
    function botonResolver(t) {
        return $('<button type="button" class="cp-btn cp-btn-primario cp-btn-resolver">')
            .text('Resolver')
            .attr('title', 'Tomar el ticket ' + Copeba.folio(t.tick_id))
            .on('click', function () {
                var $b = $(this).prop('disabled', true);
                Copeba.enviar('../../controller/ticket.php?op=tomar',
                              { tick_id: t.tick_id, solo_libre: 1 }, 'No se pudo tomar el ticket.')
                    .done(function () {
                        window.location.href = '../DetalleTicket/?ID=' + encodeURIComponent(t.tick_id);
                    })
                    .fail(function () { cargar(); });
            });
    }

    function pintarKpis(totales) {
        $.each(totales || {}, function (clave, valor) {
            $('[data-kpi="' + clave + '"]').text(Number(valor) || 0);
        });
        /* Porcentaje resuelto en la tarjeta de cerrados del Administrador */
        if (totales && totales.total !== undefined && totales.cerrados !== undefined) {
            var total = Number(totales.total) || 0;
            var pct = total ? (Number(totales.cerrados) * 100 / total).toFixed(1) : '0.0';
            var $nota = $('[data-nota="cerrados"]');
            if ($nota.length && $nota.text() === '') { $nota.text(pct + '% resueltos'); }
        }
        $('#kpis').removeClass('cp-cargando');
    }

    function pintarCategorias(categorias) {
        var $barras = $('#barras');
        if (!$barras.length) { return; }
        var suma = 0, max = 0;
        $.each(categorias, function (_, c) { suma += Number(c.total); max = Math.max(max, Number(c.total)); });
        $('#lbl_registros').text(suma + ' registros');
        $barras.empty();
        if (!categorias.length) {
            $barras.append($('<p class="cp-vacio">').text('No hay categorías registradas.'));
            return;
        }
        $.each(categorias, function (i, c) {
            var total = Number(c.total);
            var ancho = max ? Math.max(total ? 3 : 0, total * 100 / max) : 0;
            $barras.append($('<div>').append(
                $('<div class="cp-barra-fila">').append(
                    $('<span>').text(c.nombre),
                    $('<span class="cp-mono">').text(total + (total === 1 ? ' ticket' : ' tickets'))
                ),
                $('<div class="cp-barra">').addClass('c' + (i % 4)).append($('<span>').css('width', ancho + '%'))
            ));
        });
        if (suma && categorias[0] && Number(categorias[0].total)) {
            var pct = Math.round(Number(categorias[0].total) * 100 / suma);
            $('#lbl_mayor').text(categorias[0].nombre + ' concentra el ' + pct + '% de las solicitudes.');
        }
    }

    /* Recién iniciada la sesión: círculo de éxito animado, sin botón, se cierra solo */
    function bienvenida() {
        var nombre = document.body.getAttribute('data-bienvenida');
        if (nombre === null || typeof Copeba === 'undefined') { return; }
        Copeba.exito('Sesión iniciada correctamente', nombre ? '¡Bienvenido, ' + nombre + '!' : '¡Bienvenido!');
    }

    /* ---------- Panel del sistema (Administrador) ---------- */
    function lista(sel, items, vacio, fila) {
        var $l = $(sel).empty();
        if (!items || !items.length) {
            $l.append($('<li class="cp-atencion-ok">').text(vacio));
            return;
        }
        $.each(items, function (_, x) { $l.append(fila(x)); });
    }

    /* Lista con 5 por página (Copeba.paginar) en lugar de scroll */
    function listaPaginada(sel, items, vacio, fila) {
        lista(sel, items, vacio, fila);
        Copeba.paginar(sel, 5);
    }

    /* Fila de lista con flecha para ir al detalle (sin enlaces subrayados) */
    var FLECHA = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    function filaIr(titulo, href, $meta) {
        return $('<li class="cp-lista-ir">').append(
            $('<div>').append($('<strong>').text(titulo), $meta),
            $('<a class="cp-ver">').attr({ href: href, title: 'Abrir', 'aria-label': 'Abrir ' + titulo }).html(FLECHA)
        );
    }

    function horas(h) {
        h = Number(h);
        return h >= 48 ? Math.floor(h / 24) + ' días' : h + ' h';
    }

    function pintarPanelAdmin(d) {
        listaPaginada('#lista_fuera_tiempo', d.fuera_tiempo, 'Ningún ticket fuera de tiempo. ¡Bien!', function (t) {
            return filaIr(Copeba.folio(t.tick_id) + ' · ' + t.tick_titulo,
                '../DetalleTicket/?ID=' + encodeURIComponent(t.tick_id),
                $('<span class="cp-lista-meta">').append(
                    $('<span class="cp-prioridad">').addClass('p' + t.prio_id).text(t.prio_nom),
                    $('<span>').text('vencido hace ' + horas(t.horas_vencido) + ' · ' + (t.asignado || 'sin asignar'))
                ));
        });
        listaPaginada('#lista_sin_area', d.sin_area, 'Todas las cuentas tienen área.', function (u) {
            return $('<li>').append($('<strong>').text(u.nombre), $('<span class="cp-lista-meta cp-mono">').text(u.usuario));
        });
        var cuentasAreas = [];
        $.each(d.vencidas || [], function (_, u) { cuentasAreas.push({ t: 'Temporal vencida: ' + u.nombre, m: u.usuario }); });
        $.each(d.areas_vacias || [], function (_, a) { cuentasAreas.push({ t: 'Área sin integrantes: ' + a.nombre, m: 'Áreas y sedes' }); });
        listaPaginada('#lista_cuentas_areas', cuentasAreas, 'Sin temporales vencidas ni áreas vacías.', function (x) {
            return $('<li>').append($('<strong>').text(x.t), $('<span class="cp-lista-meta">').text(x.m));
        });
    }

    /* ---------- Mi día (todos los Inicios) ---------- */
    var ESTADO_TAREA = { pendiente: 'Pendiente', proceso: 'En proceso', devuelta: 'Devuelta' };

    function chipDia(fecha, hoyIso) {
        var f = String(fecha || '').slice(0, 10);
        return f < hoyIso
            ? $('<span class="cp-estado cerrado">').text('Atrasado · ' + Copeba.fecha(f))
            : $('<span class="cp-estado proceso">').text('Hoy');
    }

    function pintarMiDia(d) {
        if (!d) { return; }
        listaPaginada('#dia_tareas', d.tareas, 'No tienes tareas que venzan hoy.', function (t) {
            return filaIr(t.titulo, '../Tareas/',
                $('<span class="cp-lista-meta">').append(
                    chipDia(t.fecha, d.hoy),
                    $('<span>').text((ESTADO_TAREA[t.estado] || t.estado) + ' · ' + t.detalle)
                ));
        });
        listaPaginada('#dia_plan', d.plan, 'Nada pendiente en tu planificación para hoy.', function (a) {
            return filaIr(a.titulo, '../Planificacion/',
                $('<span class="cp-lista-meta">').append(chipDia(a.fecha, d.hoy), $('<span>').text(a.detalle)));
        });
        if (d.criticos) {
            listaPaginada('#dia_criticos', d.criticos, 'Ningún ticket crítico abierto. ¡Bien!', function (t) {
                return filaIr(Copeba.folio(t.id) + ' · ' + t.titulo,
                    '../DetalleTicket/?ID=' + encodeURIComponent(t.id),
                    $('<span class="cp-lista-meta">').append(
                        $('<span class="cp-prioridad p4">').text('Crítica'),
                        $('<span>').text(t.detalle + ' · ' + Copeba.fecha(t.fecha))
                    ));
            });
        }
    }

    $(function () {
        bienvenida();
        cargar();
        /* Si hay tabla "Sin asignar", se refresca sola para ver lo que toman los demás */
        if ($('[data-tabla="sin_asignar"]').length) {
            setInterval(function () { if (!document.hidden) { cargar(true); } }, 20000);
            $(document).on('visibilitychange', function () { if (!document.hidden) { cargar(true); } });
        }
    });

    var cargando = false;
    function cargar(silencioso) {
        if (cargando) { return; }
        cargando = true;
        $.post('../../controller/inicio.php?op=resumen')
            .always(function () { cargando = false; })
            .done(function (datos) {
                if (typeof datos === 'string') { datos = JSON.parse(datos); }
                pintarMiDia(datos.mi_dia);
                if (silencioso) {
                    pintarKpis(datos.totales);
                    pintarTabla('asignados', datos.asignados, 'No tienes tickets abiertos asignados.');
                    pintarTabla('sin_asignar', datos.sin_asignar, 'No hay tickets pendientes de asignar.');
                    return;
                }
                $('#lbl_actualizado').text('Actualizado hoy, ' + hoy());
                pintarKpis(datos.totales);
                if (datos.categorias) { pintarCategorias(datos.categorias); }
                pintarTabla('recientes', datos.recientes, 'Todavía no hay tickets.');
                pintarTabla('asignados', datos.asignados, 'No tienes tickets abiertos asignados.');
                pintarTabla('sin_asignar', datos.sin_asignar, 'No hay tickets pendientes de asignar.');
                if (datos.vista === 'admin') { pintarPanelAdmin(datos); }
            })
            .fail(function () {
                if (silencioso) { return; }
                $('[data-tabla] ').html('<tr><td colspan="7" class="cp-vacio">No se pudo cargar la información. Recarga la página.</td></tr>');
                $('#kpis').removeClass('cp-cargando');
                $('#dia_tareas, #dia_plan, #dia_criticos').html('<li class="cp-vacio">No se pudo cargar.</li>');
            });
    }
})();
