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
        ['height', ['height']]
    ];

    var BARRA_SIMPLE = [['style', ['bold', 'italic']], ['para', ['ul', 'ol']], ['insert', ['link']]];

    /* opciones: { barra: 'simple', ayuda: 'texto de ejemplo' } */
    function editor(selector, alto, opciones) {
        opciones = opciones || {};
        return $(selector).summernote({
            height: alto || 200,
            lang: 'es-ES',
            toolbar: opciones.barra === 'simple' ? BARRA_SIMPLE : BARRA_EDITOR,
            placeholder: opciones.ayuda || '',
            disableDragAndDrop: true
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

    return {
        exito: exito, aviso: aviso, error: error, confirmar: confirmar, pedirTexto: pedirTexto, dialogo: dialogo,
        leer: leer, enviar: enviar,
        editor: editor,
        param: param, folio: folio, fecha: fecha, hora: hora, iniciales: iniciales, estado: estado
    };
})(jQuery);
