var tick_id = Copeba.param('ID');

$(function () {
    Copeba.editor('#tickd_descrip', 400);
    Copeba.editor('#tickd_descripusu', 400).summernote('disable');
    Copeba.tabla('#documentos_data', '../../controller/documento.php?op=listar', { tick_id: tick_id });
    listardetalle();
});

$(document).on('click', '#btnenviar', function () {
    if ($('#tickd_descrip').summernote('isEmpty')) {
        Copeba.aviso('Escribe una respuesta antes de enviar.');
        return;
    }
    /* El autor de la respuesta lo toma el servidor de la sesión */
    Copeba.enviar('../../controller/ticket.php?op=insertdetalle',
                  { tick_id: tick_id, tickd_descrip: $('#tickd_descrip').val() }, 'No se pudo registrar.')
        .done(function () {
            listardetalle();
            $('#tickd_descrip').summernote('reset');
            Copeba.exito('Respuesta registrada.');
        });
});

$(document).on('click', '#btncerrarticket', function () {
    Copeba.confirmar('¿Está seguro de cerrar el ticket?', function () {
        Copeba.enviar('../../controller/ticket.php?op=update', { tick_id: tick_id }, 'No se pudo cerrar el ticket.')
            .done(function () {
                $.post('../../controller/email.php?op=ticket_cerrado', { tick_id: tick_id });
                listardetalle();
                Copeba.exito('Ticket cerrado correctamente.');
            });
    });
});

function listardetalle() {
    $.post('../../controller/ticket.php?op=listardetalle', { tick_id: tick_id }, function (data) {
        $('#lbldetalle').html(data);
    });

    $.post('../../controller/ticket.php?op=mostrar', { tick_id: tick_id }, function (data) {
        data = Copeba.leer(data);
        if (!data) { return; }
        /* tick_estado llega como HTML de la etiqueta generada por el servidor */
        $('#lblestado').html(data.tick_estado);
        $('#lblnomusuario').html(data.usu_nom + ' ' + data.usu_ape);   /* el servidor ya lo envía escapado */
        $('#lblfechcrea').text(data.fech_crea);
        $('#lblnomidticket').text('Detalle Ticket - ' + data.tick_id);
        $('#cat_nom').val(data.cat_nom);
        $('#tick_titulo').val(data.tick_titulo);
        $('#tickd_descripusu').summernote('code', data.tick_descrip);
        if (data.tick_estado_texto === 'Cerrado') {
            $('#pnldetalle').hide();
        }
    });
}
