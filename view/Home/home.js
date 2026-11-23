/* Inicio: pide un solo resumen al servidor y lo pinta según el rol.
   Todo el texto que viene de la base se inserta con .text() (nunca como HTML). */
(function () {

    function fecha(valor) {
        if (!valor) { return ''; }
        var p = String(valor).substr(0, 10).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : valor;
    }

    function folio(id) {
        return '#' + ('000' + id).slice(-3);
    }

    function hoy() {
        var d = new Date();
        return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
    }

    function estado(t) {
        var abierto = t.tick_estado === 'Abierto';
        return $('<span class="cp-estado">')
            .addClass(abierto ? 'abierto' : 'cerrado')
            .text(abierto ? 'Abierto' : 'Cerrado');
    }

    function soporte(t) {
        if (t.asignado) { return $('<span>').text(t.asignado); }
        return t.tick_estado === 'Abierto'
            ? $('<span class="cp-estado pendiente">').text('Pendiente')
            : $('<span class="cp-suave">').text('—');
    }

    function botonVer(id) {
        return $('<a class="cp-ver" target="_blank" rel="noopener">')
            .attr('href', '../DetalleTicket/?ID=' + encodeURIComponent(id))
            .attr('title', 'Ver ticket ' + folio(id))
            .attr('aria-label', 'Ver ticket ' + folio(id))
            .html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>');
    }

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
                $('<td class="cp-mono">').text(folio(t.tick_id)),
                $('<td>').text(t.tick_titulo),
                $('<td class="cp-suave">').text(t.cat_nom),
                $('<td>').append(estado(t)),
                $('<td>').append(soporte(t)),
                $('<td class="cp-mono">').text(fecha(t.fech_crea)),
                $('<td>').append(botonVer(t.tick_id))
            ));
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
        if (nombre === null || typeof swal !== 'function') { return; }
        swal({
            title: nombre ? '¡Bienvenido, ' + nombre + '!' : '¡Bienvenido!',
            text: 'Sesión iniciada correctamente',
            type: 'success',
            timer: 1600,
            showConfirmButton: false,
            allowOutsideClick: true
        });
    }

    $(function () {
        bienvenida();
        $.post('../../controller/inicio.php?op=resumen')
            .done(function (datos) {
                if (typeof datos === 'string') { datos = JSON.parse(datos); }
                $('#lbl_actualizado').text('Actualizado hoy, ' + hoy());
                pintarKpis(datos.totales);
                if (datos.categorias) { pintarCategorias(datos.categorias); }
                pintarTabla('recientes', datos.recientes, 'Todavía no hay tickets.');
                pintarTabla('asignados', datos.asignados, 'No tienes tickets abiertos asignados.');
                pintarTabla('sin_asignar', datos.sin_asignar, 'No hay tickets pendientes de asignar.');
            })
            .fail(function () {
                $('[data-tabla] ').html('<tr><td colspan="7" class="cp-vacio">No se pudo cargar la información. Recarga la página.</td></tr>');
                $('#kpis').removeClass('cp-cargando');
            });
    });
})();
