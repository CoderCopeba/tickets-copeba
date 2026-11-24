var ADJUNTO_MAX_BYTES = 10 * 1024 * 1024;
var ADJUNTO_PERMITIDOS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

/* Archivos elegidos (se pueden quitar uno a uno antes de enviar) */
var adjuntos = [];

$(function () {
    Copeba.editor('#tick_descrip', 160, {
        barra: 'simple',
        ayuda: 'Describe qué ocurrió, desde cuándo sucede y si aparece algún código de error...'
    });

    $('#fileElem').on('change', function () {
        agregarArchivos(this.files);
        this.value = '';            /* permite volver a elegir el mismo archivo */
    });

    /* Arrastrar y soltar sobre la zona de adjuntos */
    $('.cp-adjuntos')
        .on('dragover', function (e) { e.preventDefault(); $(this).addClass('cp-arrastrando'); })
        .on('dragleave drop', function () { $(this).removeClass('cp-arrastrando'); })
        .on('drop', function (e) {
            e.preventDefault();
            agregarArchivos(e.originalEvent.dataTransfer.files);
        });

    $('#lista_archivos').on('click', 'button', function () {
        adjuntos.splice(Number($(this).data('i')), 1);
        pintarArchivos();
    });

    $('#ticket_form').on('submit', guardar);
    $('#btn_cancelar').on('click', cancelar);
    $('#tick_titulo').on('input', function () { $(this).removeClass('cp-invalido'); });
});

/* ---------- Adjuntos ---------- */
function problemaArchivo(archivo) {
    var ext = archivo.name.split('.').pop().toLowerCase();
    if (archivo.name.indexOf('.') === -1 || ADJUNTO_PERMITIDOS.indexOf(ext) === -1) {
        return 'Tipo de archivo no permitido: ' + archivo.name + '. Permitidos: ' + ADJUNTO_PERMITIDOS.join(', ') + '.';
    }
    if (archivo.size > ADJUNTO_MAX_BYTES) {
        return 'El archivo ' + archivo.name + ' supera el máximo de 10 MB.';
    }
    return null;
}

function agregarArchivos(lista) {
    for (var i = 0; i < lista.length; i++) {
        var problema = problemaArchivo(lista[i]);
        if (problema) { Copeba.aviso(problema); continue; }
        adjuntos.push(lista[i]);
    }
    pintarArchivos();
}

function tamano(bytes) {
    return bytes < 1024 * 1024 ? Math.ceil(bytes / 1024) + ' KB' : (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

function pintarArchivos() {
    var $lista = $('#lista_archivos').empty();
    $.each(adjuntos, function (i, archivo) {
        $lista.append($('<li>').append(
            $('<span>').text(archivo.name),
            $('<span class="cp-suave">').text(tamano(archivo.size)),
            $('<button type="button">').attr({ 'data-i': i, 'aria-label': 'Quitar ' + archivo.name, title: 'Quitar' })
                .html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>')
        ));
    });
}

/* ---------- Formulario ---------- */
function tieneDatos() {
    return $.trim($('#tick_titulo').val()) !== '' || !$('#tick_descrip').summernote('isEmpty') || adjuntos.length > 0;
}

function limpiar() {
    $('#ticket_form')[0].reset();
    $('#tick_descrip').summernote('reset');
    adjuntos = [];
    pintarArchivos();
}

function cancelar() {
    if (!tieneDatos()) { window.location.href = '../Home/'; return; }
    Copeba.confirmar('¿Descartar este ticket? Se perderá lo que has escrito.', function () {
        limpiar();
        Copeba.exito('Ticket descartado.');
        setTimeout(function () { window.location.href = '../Home/'; }, 900);
    });
}

function guardar(e) {
    e.preventDefault();
    var $titulo = $('#tick_titulo');
    if ($.trim($titulo.val()) === '') {
        $titulo.addClass('cp-invalido').trigger('focus');
        Copeba.aviso('Escribe el título del problema o solicitud.');
        return;
    }
    if ($('#tick_descrip').summernote('isEmpty')) {
        Copeba.aviso('Describe el problema antes de enviar.');
        return;
    }

    var formData = new FormData($('#ticket_form')[0]);
    formData.delete('fileElem');
    $.each(adjuntos, function (_, archivo) { formData.append('files[]', archivo); });

    var $boton = $('#btn_guardar').prop('disabled', true);
    $.ajax({
        url: '../../controller/ticket.php?op=insert',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false
    }).done(function (data) {
        data = Copeba.leer(data);
        if (data && data.error) { Copeba.aviso(data.error, 'No se pudo enviar'); return; }
        if (!Array.isArray(data) || !data.length) { Copeba.error('No se pudo enviar el ticket. Inténtalo de nuevo.', 'No se pudo enviar'); return; }

        $.post('../../controller/email.php?op=ticket_abierto', { tick_id: data[0].tick_id });
        limpiar();
        Copeba.exito('Te avisaremos cuando un técnico lo atienda.', 'Ticket ' + Copeba.folio(data[0].tick_id) + ' enviado');
    }).fail(function (xhr) {
        if (xhr.status === 401) { window.location.href = '../../index.php'; return; }
        var r = Copeba.leer(xhr.responseText);
        Copeba.error((r && r.message) ? r.message : 'No se pudo enviar el ticket. Revisa tu conexión e inténtalo de nuevo.', 'No se pudo enviar');
    }).always(function () {
        $boton.prop('disabled', false);
    });
}
