/* ==========================================================================
   Recursos compartidos de Copeba Tickets (se carga en todas las pantallas).
   Úsalos en lugar de repetir código:
     Copeba.exito(texto)                 círculo de éxito, sin botón, se cierra solo
     Copeba.aviso(texto) / .error(texto) mensaje con botón "Entendido"
     Copeba.confirmar(texto, fn, {peligro:true})
     Copeba.enviar(url, datos, msgError) POST que espera {status:"ok"} (acepta FormData)
     Copeba.leer(respuesta)              JSON seguro (devuelve null si no es JSON)
     Copeba.tabla(selector, url, datos)  DataTable con la configuración común
     Copeba.editor(selector, alto)       editor Summernote con la barra común
     Copeba.param(nombre)                parámetro de la URL (?ID=...)
     Copeba.folio(id), Copeba.fecha(valor)
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

    /* ---------- Tablas (DataTables) ---------- */
    var IDIOMA_TABLAS = {
        sProcessing: 'Procesando...',
        sLengthMenu: 'Mostrar _MENU_ registros',
        sZeroRecords: 'No se encontraron resultados',
        sEmptyTable: 'Ningún dato disponible en esta tabla',
        sInfo: 'Mostrando un total de _TOTAL_ registros',
        sInfoEmpty: 'Mostrando un total de 0 registros',
        sInfoFiltered: '(filtrado de un total de _MAX_ registros)',
        sSearch: 'Buscar:',
        sInfoThousands: ',',
        sLoadingRecords: 'Cargando...',
        oPaginate: { sFirst: 'Primero', sLast: 'Último', sNext: 'Siguiente', sPrevious: 'Anterior' },
        oAria: {
            sSortAscending: ': Activar para ordenar la columna de manera ascendente',
            sSortDescending: ': Activar para ordenar la columna de manera descendente'
        }
    };

    /* Valores por defecto para TODAS las tablas del sistema */
    if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            dom: 'Bfrtip',
            searching: true,
            lengthChange: false,
            colReorder: true,
            buttons: ['copyHtml5', 'excelHtml5', 'csvHtml5', 'pdfHtml5'],
            destroy: true,
            responsive: true,
            info: true,
            pageLength: 10,
            autoWidth: false,
            language: IDIOMA_TABLAS
        });
    }

    /* Tabla cargada por POST desde un controlador. "opciones" permite ajustar
       cualquier valor (p. ej. {ordering:false}). */
    function tabla(selector, url, datos, opciones) {
        return $(selector).DataTable($.extend(true, {
            ajax: {
                url: url,
                type: 'POST',
                data: datos || {},
                dataType: 'json',
                error: function (xhr) {
                    if (xhr.status === 401) { window.location.href = '../../index.php'; return; }
                    aviso(textoError(leer(xhr.responseText), 'No se pudo cargar la tabla.'));
                }
            }
        }, opciones || {}));
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

    function editor(selector, alto) {
        return $(selector).summernote({ height: alto || 200, lang: 'es-ES', toolbar: BARRA_EDITOR });
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

    return {
        exito: exito, aviso: aviso, error: error, confirmar: confirmar,
        leer: leer, enviar: enviar,
        tabla: tabla, editor: editor,
        param: param, folio: folio, fecha: fecha
    };
})(jQuery);
