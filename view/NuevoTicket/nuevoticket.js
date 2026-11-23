var ADJUNTO_MAX_BYTES = 10 * 1024 * 1024;
var ADJUNTO_PERMITIDOS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

$(function () {
    Copeba.editor('#tick_descrip', 150);
    $.post('../../controller/categoria.php?op=combo', function (data) {
        $('#cat_id').html(data);
    });
    $('#ticket_form').on('submit', guardar);
});

/* Devuelve el texto del problema o null si los adjuntos son válidos
   (el servidor vuelve a comprobarlo todo) */
function adjuntoInvalido(archivos) {
    for (var i = 0; i < archivos.length; i++) {
        var nombre = archivos[i].name;
        var ext = nombre.split('.').pop().toLowerCase();
        if (nombre.indexOf('.') === -1 || ADJUNTO_PERMITIDOS.indexOf(ext) === -1) {
            return 'Tipo de archivo no permitido: ' + nombre + '. Permitidos: ' + ADJUNTO_PERMITIDOS.join(', ');
        }
        if (archivos[i].size > ADJUNTO_MAX_BYTES) {
            return 'El archivo ' + nombre + ' supera el máximo de 10 MB.';
        }
    }
    return null;
}

function guardar(e) {
    e.preventDefault();
    if ($('#tick_descrip').summernote('isEmpty') || $.trim($('#tick_titulo').val()) === '') {
        Copeba.aviso('Escribe el título y la descripción del problema.');
        return;
    }

    var archivos = $('#fileElem')[0].files;
    var problema = adjuntoInvalido(archivos);
    if (problema) {
        Copeba.aviso(problema);
        return;
    }

    /* El input se llama "fileElem"; se envían como files[] */
    var formData = new FormData($('#ticket_form')[0]);
    formData.delete('fileElem');
    for (var j = 0; j < archivos.length; j++) {
        formData.append('files[]', archivos[j]);
    }

    $.ajax({
        url: '../../controller/ticket.php?op=insert',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false
    }).done(function (data) {
        data = Copeba.leer(data);
        if (data && data.error) { Copeba.aviso(data.error); return; }
        if (!Array.isArray(data) || !data.length) { Copeba.error('No se pudo registrar el ticket.'); return; }

        $.post('../../controller/email.php?op=ticket_abierto', { tick_id: data[0].tick_id });
        $('#ticket_form')[0].reset();
        $('#tick_descrip').summernote('reset');
        Copeba.exito('Ticket ' + Copeba.folio(data[0].tick_id) + ' registrado.');
    }).fail(function () {
        Copeba.error('No se pudo registrar el ticket.');
    });
}
