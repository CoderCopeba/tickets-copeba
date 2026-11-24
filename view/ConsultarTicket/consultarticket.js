var atiende = $('#soporte_idx').val() === '1';

$(function () {
    /* Colaborador: solo sus tickets. Quien atiende tickets: todos, y puede asignar (el servidor lo exige igual) */
    if (!atiende) {
        Copeba.tabla('#ticket_data', '../../controller/ticket.php?op=listar_x_usu', {}, { ordering: false });
    } else {
        Copeba.tabla('#ticket_data', '../../controller/ticket.php?op=listar');
        $.post('../../controller/usuario.php?op=combo', function (data) {
            $('#usu_asig').html(data);
        });
    }

    $('#ticket_form').on('submit', guardar);
});

function recargar() {
    $('#ticket_data').DataTable().ajax.reload();
}

function asignar(tick_id) {
    $('#tick_id').val(tick_id);
    $('#mdltitulo').html('Asignar Agente');
    $('#modalasignar').modal('show');
}

function guardar(e) {
    e.preventDefault();
    var tick_id = $('#tick_id').val();
    Copeba.enviar('../../controller/ticket.php?op=asignar', new FormData($('#ticket_form')[0]), 'No se pudo asignar.')
        .done(function () {
            $.post('../../controller/email.php?op=ticket_asignado', { tick_id: tick_id });
            $('#modalasignar').modal('hide');
            recargar();
            Copeba.exito('Ticket asignado correctamente.');
        });
}

function CambiarEstado(tick_id) {
    Copeba.confirmar('¿Está seguro de reabrir el ticket?', function () {
        Copeba.enviar('../../controller/ticket.php?op=reabrir', { tick_id: tick_id }, 'No se pudo reabrir.')
            .done(function () {
                recargar();
                Copeba.exito('Ticket abierto nuevamente.');
            });
    });
}
