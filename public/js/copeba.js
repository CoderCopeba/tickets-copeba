/* ==========================================================================
   Recursos compartidos de Copeba Tickets (se carga en todas las pantallas).
   Úsalos en lugar de repetir código:
     Copeba.exito(texto)                 círculo de éxito, sin botón, se cierra solo
     Copeba.aviso(texto) / .error(texto) mensaje con botón "Entendido"
     Copeba.confirmar(texto, fn, {peligro:true})
     Copeba.pedirTexto(texto, ayuda, fn)  pide un texto obligatorio (motivo, etc.)
     Copeba.dialogo(id) / (id, false)     abre / cierra un <dialog class="cp-dialogo">
     Copeba.enviar(url, datos, msgError) POST que espera {status:"ok"} (acepta FormData)
     Copeba.leer(respuesta)              JSON seguro (devuelve null si no es JSON)
     Copeba.editor(selector, alto, {barra:'simple', ayuda:'...'}) editor Summernote
     Copeba.param(nombre)                parámetro de la URL (?ID=...)
     Copeba.folio(id), Copeba.fecha(valor), Copeba.hora(valor), Copeba.iniciales(nombre)
     Copeba.estado(texto)                etiqueta de color del estado del ticket
   ========================================================================== */
var Copeba = (function ($) {
    'use strict';

    var TITULO = 'Copeba Tickets';

    /* ---------- Alertas ---------- */
    function exito(texto, titulo) {
        swal({
            title: titulo || '¡Listo!',
            text: texto || '',
            type: 'success',
            timer: 1000,
            showConfirmButton: false,
            allowOutsideClick: true
        });
    }

    function mensaje(tipo, texto, titulo) {
        swal({
            title: titulo || (tipo === 'error' ? 'No se pudo completar' : 'Atención'),
            text: texto,
            type: tipo,
            confirmButtonText: 'Entendido',
            confirmButtonClass: tipo === 'error' ? 'btn-danger' : 'btn-warning'
        });
    }
    function aviso(texto, titulo) { mensaje('warning', texto, titulo); }
    function error(texto, titulo) { mensaje('error', texto, titulo); }

    /* La ventana queda abierta al confirmar para que la siguiente alerta
       (éxito o error) la reemplace sin parpadeo. */
    function confirmar(texto, alConfirmar, opciones) {
        var peligro = opciones && opciones.peligro;
        swal({
            title: TITULO,
            text: texto,
            type: peligro ? 'error' : 'warning',
            showCancelButton: true,
            confirmButtonClass: peligro ? 'btn-danger' : 'btn-warning',
            confirmButtonText: 'Sí',
            cancelButtonText: 'No',
            closeOnConfirm: false
        }, function (ok) { if (ok) { alConfirmar(); } });
    }

    /* Pide un texto obligatorio (p. ej. un motivo). alAceptar(texto) */
    function pedirTexto(texto, ayuda, alAceptar) {
        swal({
            title: TITULO,
            text: texto,
            type: 'input',
            inputPlaceholder: ayuda || '',
            showCancelButton: true,
            confirmButtonText: 'Aceptar',
            cancelButtonText: 'Cancelar',
            confirmButtonClass: 'btn-warning',
            closeOnConfirm: false
        }, function (valor) {
            if (valor === false) { return; }
            if ($.trim(valor) === '') { swal.showInputError('Este dato es obligatorio.'); return; }
            alAceptar($.trim(valor));
        });
    }

    /* Ventanas <dialog>: se abren con show() (no showModal) para que las alertas
       queden por encima; el fondo oscuro lo pone la clase del body. Esc cierra. */
    function dialogo(id, abrir) {
        var d = document.getElementById(id);
        if (!d) { return; }
        if (abrir === false) { if (d.open) { d.close(); } return; }
        if (!d.dataset.cpCierre) {          /* "close" no burbujea: se engancha una vez */
            d.dataset.cpCierre = '1';
            /* X para cerrar en la esquina, igual en todas las ventanas */
            var $cab = $(d).find('> header, > form > header').first();
            if ($cab.length && !$cab.find('.cp-dialogo-x').length) {
                $cab.append($('<button type="button" class="cp-dialogo-x" aria-label="Cerrar">').html(
                    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>')
                    .on('click', function () { d.close(); }));
            }
            d.addEventListener('close', function () {
                if (!$('dialog.cp-dialogo[open]').length) { $('body').removeClass('cp-con-dialogo'); }
            });
        }
        if (!d.open) { d.show(); }
        $('body').addClass('cp-con-dialogo');
        var $f = $(d).find('input:not([type=hidden]),select,textarea').first();
        if ($f.length) { $f.trigger('focus'); }
    }
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && !$('.sweet-alert.visible').length) {
            var abiertos = $('dialog.cp-dialogo[open]');
            if (abiertos.length) { abiertos.last()[0].close(); }
        }
    });

    /* ---------- Peticiones al servidor ---------- */
    function leer(respuesta) {
        if (typeof respuesta !== 'string') { return respuesta || null; }
        try { return JSON.parse(respuesta); } catch (e) { return null; }
    }

    function textoError(resp, porDefecto) {
        return (resp && (resp.message || resp.error)) ? (resp.message || resp.error) : porDefecto;
    }

    /* POST a un controlador que responde {status:"ok"} o {status:"error", message}.
       Si falla, muestra el aviso; devuelve una promesa que solo se cumple si todo fue bien. */
    function enviar(url, datos, porDefecto) {
        var formulario = (typeof FormData !== 'undefined') && (datos instanceof FormData);
        var resultado = $.Deferred();
        porDefecto = porDefecto || 'No se pudo completar la operación.';

        $.ajax({
            url: url,
            type: 'POST',
            data: datos || {},
            processData: !formulario,
            contentType: formulario ? false : 'application/x-www-form-urlencoded; charset=UTF-8'
        }).done(function (r) {
            r = leer(r);
            if (r && r.status === 'ok') { resultado.resolve(r); }
            else { aviso(textoError(r, porDefecto)); resultado.reject(r); }
        }).fail(function (xhr) {
            var r = leer(xhr.responseText);
            if (xhr.status === 401) { window.location.href = '../../index.php'; return; }
            aviso(textoError(r, porDefecto));
            resultado.reject(r);
        });
        return resultado.promise();
    }

    /* ---------- Editor de texto (Summernote) ---------- */
    var BARRA_EDITOR = [
        ['style', ['bold', 'italic', 'underline', 'clear']],
        ['font', ['strikethrough', 'superscript', 'subscript']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['height', ['height']],
        ['insert', ['link', 'picture']]
    ];

    var BARRA_SIMPLE = [['style', ['bold', 'italic']], ['para', ['ul', 'ol']], ['insert', ['link', 'picture']]];

    /* Imágenes en el texto (capturas de un error): se reducen en el navegador a un tamaño
       razonable y van dentro del propio texto (el servidor solo acepta PNG/JPEG/GIF/WEBP,
       config/html.php). Se pueden pegar con Ctrl+V, arrastrar o elegir con el botón. */
    var IMAGEN_LADO_MAX = 1600;                 /* px del lado mayor */
    var IMAGEN_BYTES_MAX = 1900000;             /* el servidor admite hasta 2 000 000 */
    var IMAGEN_TIPOS = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    function reducirImagen(archivo, listo) {
        if (IMAGEN_TIPOS.indexOf(archivo.type) === -1) {
            aviso('Solo se pueden insertar imágenes PNG, JPG, GIF o WEBP.');
            return;
        }
        var lector = new FileReader();
        lector.onload = function () {
            var img = new Image();
            img.onload = function () {
                var escala = Math.min(1, IMAGEN_LADO_MAX / Math.max(img.width, img.height));
                var lienzo = document.createElement('canvas');
                lienzo.width = Math.round(img.width * escala);
                lienzo.height = Math.round(img.height * escala);
                var ctx = lienzo.getContext('2d');
                ctx.fillStyle = '#fff';                     /* fondo blanco si la PNG es transparente */
                ctx.fillRect(0, 0, lienzo.width, lienzo.height);
                ctx.drawImage(img, 0, 0, lienzo.width, lienzo.height);
                /* PNG nítida para capturas pequeñas; JPEG si pesa demasiado */
                var datos = lienzo.toDataURL('image/png');
                var calidad = 0.85;
                while (datos.length > IMAGEN_BYTES_MAX / 2 && calidad >= 0.45) {
                    datos = lienzo.toDataURL('image/jpeg', calidad);
                    calidad -= 0.1;
                }
                if (datos.length > IMAGEN_BYTES_MAX) { aviso('La imagen es demasiado grande. Recórtala e inténtalo de nuevo.'); return; }
                listo(datos);
            };
            img.onerror = function () { aviso('No se pudo leer la imagen.'); };
            img.src = lector.result;
        };
        lector.readAsDataURL(archivo);
    }

    /* opciones: { barra: 'simple', ayuda: 'texto de ejemplo' } */
    function editor(selector, alto, opciones) {
        opciones = opciones || {};
        var $editor = $(selector);
        return $editor.summernote({
            height: alto || 200,
            lang: 'es-ES',
            toolbar: opciones.barra === 'simple' ? BARRA_SIMPLE : BARRA_EDITOR,
            placeholder: opciones.ayuda || '',
            /* Sus ventanas (enlace, imagen) van en <body>: dentro de un <dialog> quedaban
               debajo de su propio fondo gris y bloqueaban el editor */
            dialogsInBody: true,
            callbacks: {
                /* Botón de imagen, pegar (Ctrl+V) o arrastrar */
                onImageUpload: function (archivos) {
                    $.each(archivos, function (i, archivo) {
                        reducirImagen(archivo, function (datos) { $editor.summernote('insertImage', datos); });
                    });
                }
            }
        });
    }

    /* ---------- Utilidades ---------- */
    function param(nombre) {
        return new URLSearchParams(window.location.search).get(nombre);
    }

    function folio(id) {
        return '#' + ('000' + id).slice(-3);
    }

    /* "2026-11-23 10:30:00" -> "23/11/2026" */
    function fecha(valor) {
        if (!valor) { return ''; }
        var p = String(valor).substr(0, 10).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : String(valor);
    }

    /* "2026-11-23 10:30:00" -> "10:30" */
    function hora(valor) {
        return valor ? String(valor).substr(11, 5) : '';
    }

    /* "Ana Martínez" -> "AM" */
    function iniciales(nombre) {
        var partes = String(nombre || '').trim().split(/\s+/);
        return ((partes[0] || '').charAt(0) + (partes[1] || '').charAt(0)).toUpperCase() || '?';
    }

    /* Etiqueta de estado del ticket: Abierto (verde), En espera (ámbar), Cerrado (rojo) */
    var CLASE_ESTADO = { 'Abierto': 'abierto', 'En espera': 'pendiente', 'Cerrado': 'cerrado' };
    function estado(texto) {
        return $('<span class="cp-estado">').addClass(CLASE_ESTADO[texto] || 'abierto').text(texto);
    }

    /* ---------- Calendario propio para <input type="date"> ----------
       El calendario del navegador solo avanza con la rueda y no muestra bien en qué
       mes vas. Este muestra siempre mes y año (con listas para saltar a cualquiera),
       flechas, "Hoy" y "Borrar". El <input> original queda oculto y conserva el valor
       en formato AAAA-MM-DD, así el resto del código no cambia (.val() y 'change').
       Se aplica solo a todo input[type=date] al cargar la página. Si cambias el valor
       por código, avisa con: $('#campo').val('2026-01-31').trigger('cp-fecha'); */
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var DIAS = ['LU', 'MA', 'MI', 'JU', 'VI', 'SA', 'DO'];

    function dosDigitos(n) { return (n < 10 ? '0' : '') + n; }
    function aIso(d) { return d.getFullYear() + '-' + dosDigitos(d.getMonth() + 1) + '-' + dosDigitos(d.getDate()); }
    function deIso(v) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
    }

    function calendario(input) {
        var $in = $(input);
        if ($in.data('cp-calendario')) { return; }
        $in.data('cp-calendario', true);

        var $boton = $('<button type="button" class="cp-control cp-cal-boton" aria-haspopup="dialog">');
        var $panel = $('<div class="cp-cal" role="dialog" aria-label="Elegir fecha" hidden>');
        var $mes = $('<select class="cp-cal-mes" aria-label="Mes">');
        var $anio = $('<select class="cp-cal-anio" aria-label="Año">');
        var $rejilla = $('<div class="cp-cal-dias">');
        var visto = new Date();

        MESES.forEach(function (n, i) { $mes.append($('<option>').val(i).text(n)); });
        var esteAnio = new Date().getFullYear();
        for (var a = esteAnio + 1; a >= esteAnio - 10; a--) { $anio.append($('<option>').val(a).text(a)); }

        $panel.append(
            $('<div class="cp-cal-cabecera">').append(
                $('<button type="button" class="cp-cal-flecha" data-paso="-1" aria-label="Mes anterior">').text('‹'),
                $mes, $anio,
                $('<button type="button" class="cp-cal-flecha" data-paso="1" aria-label="Mes siguiente">').text('›')
            ),
            $('<div class="cp-cal-semana">').append(DIAS.map(function (d) { return $('<span>').text(d); })),
            $rejilla,
            $('<div class="cp-cal-pie">').append(
                $('<button type="button" data-accion="borrar">').text('Borrar'),
                $('<button type="button" data-accion="hoy">').text('Hoy')
            )
        );

        $in.attr('type', 'hidden').after($boton, $panel);
        $in.parent().addClass('cp-cal-contenedor');

        function mostrarValor() {
            var d = deIso($in.val());
            $boton.text(d ? fecha($in.val()) : 'dd/mm/aaaa').toggleClass('vacio', !d);
        }

        function pintar() {
            var anio = visto.getFullYear(), m = visto.getMonth();
            if (!$anio.find('option[value="' + anio + '"]').length) {
                $anio.append($('<option>').val(anio).text(anio));
            }
            $mes.val(m); $anio.val(anio);
            var elegido = $in.val(), hoy = aIso(new Date());
            var inicio = (new Date(anio, m, 1).getDay() + 6) % 7;   /* semana empieza en lunes */
            var total = new Date(anio, m + 1, 0).getDate();
            $rejilla.empty();
            for (var i = 0; i < inicio; i++) { $rejilla.append('<span></span>'); }
            for (var dia = 1; dia <= total; dia++) {
                var iso = aIso(new Date(anio, m, dia));
                $rejilla.append($('<button type="button">').text(dia).attr('data-iso', iso)
                    .toggleClass('elegido', iso === elegido).toggleClass('hoy', iso === hoy));
            }
        }

        function abrir() {
            visto = deIso($in.val()) || new Date();
            visto.setDate(1);
            pintar();
            $panel.removeClass('arriba').prop('hidden', false);
            /* Si no cabe debajo (dentro de la ventana o del cuadro que lo contiene),
               se abre hacia arriba: así la pantalla no salta para mostrarlo */
            var caja = $boton[0].getBoundingClientRect();
            var limiteAbajo = window.innerHeight, limiteArriba = 0;
            $boton.parents().each(function () {
                var desborde = $(this).css('overflow-y');
                if (desborde === 'auto' || desborde === 'scroll' || desborde === 'hidden') {
                    var r = this.getBoundingClientRect();
                    limiteAbajo = Math.min(limiteAbajo, r.bottom);
                    limiteArriba = Math.max(limiteArriba, r.top);
                }
            });
            var alto = $panel.outerHeight() + 8;
            if (limiteAbajo - caja.bottom < alto && caja.top - limiteArriba >= alto) { $panel.addClass('arriba'); }
            $rejilla.find('.elegido, .hoy').first().trigger('focus');
        }
        function cerrar() { $panel.prop('hidden', true); }
        function elegir(iso) {
            $in.val(iso).trigger('change');
            mostrarValor();
            cerrar();
            $boton.trigger('focus');
        }

        $boton.on('click', function () { if ($panel.prop('hidden')) { abrir(); } else { cerrar(); } });
        $panel.on('click', '.cp-cal-flecha', function () {
            visto.setMonth(visto.getMonth() + Number($(this).data('paso'))); pintar();
        });
        $mes.on('change', function () { visto.setMonth(Number($mes.val())); pintar(); });
        $anio.on('change', function () { visto.setFullYear(Number($anio.val())); pintar(); });
        $rejilla.on('click', 'button', function () { elegir($(this).attr('data-iso')); });
        $panel.on('click', '[data-accion="hoy"]', function () { elegir(aIso(new Date())); });
        $panel.on('click', '[data-accion="borrar"]', function () { elegir(''); });
        $panel.on('keydown', function (e) { if (e.key === 'Escape') { cerrar(); $boton.trigger('focus'); } });
        $(document).on('mousedown', function (e) {
            if (!$panel.prop('hidden') && !$(e.target).closest($panel.add($boton)).length) { cerrar(); }
        });
        $in.on('cp-fecha', mostrarValor);
        mostrarValor();
    }

    $(function () { $('input[type="date"]').each(function () { calendario(this); }); });

    return {
        exito: exito, aviso: aviso, error: error, confirmar: confirmar, pedirTexto: pedirTexto, dialogo: dialogo,
        leer: leer, enviar: enviar,
        editor: editor, calendario: calendario,
        param: param, folio: folio, fecha: fecha, hora: hora, iniciales: iniciales, estado: estado
    };
})(jQuery);
