$(function () {
    Copeba.tabla('#usuario_data', '../../controller/usuario.php?op=listar');
    $('#usuario_form').on('submit', guardaryeditar);
});

function recargar() {
    $('#usuario_data').DataTable().ajax.reload();
}

function guardaryeditar(e) {
    e.preventDefault();
    var usu_usuario = $.trim($('#usu_usuario').val()).toLowerCase();
    if (!/^[a-z0-9._-]{3,30}$/.test(usu_usuario)) {
        Copeba.aviso('Nombre de usuario no válido. Use de 3 a 30 caracteres: letras sin acentos (a-z), números, punto (.), guion (-) o guion bajo (_).');
        return;
    }
    $('#usu_usuario').val(usu_usuario);

    /* Alta: contraseña obligatoria. Edición: vacía = no cambiarla */
    if ($('#usu_id').val() === '' && $('#usu_pass').val() === '') {
        Copeba.aviso('La contraseña es obligatoria para un usuario nuevo.');
        return;
    }

    Copeba.enviar('../../controller/usuario.php?op=guardaryeditar', new FormData($('#usuario_form')[0]), 'No se pudo guardar el usuario.')
        .done(function () {
            $('#usuario_form')[0].reset();
            $('#modalmantenimiento').modal('hide');
            recargar();
            Copeba.exito('Usuario guardado.');
        });
}

function editar(usu_id) {
    $('#mdltitulo').html('Editar Registro');
    $.post('../../controller/usuario.php?op=mostrar', { usu_id: usu_id }, function (data) {
        data = Copeba.leer(data);
        if (!data) { return; }
        $('#usu_id').val(data.usu_id);
        $('#usu_nom').val(data.usu_nom);
        $('#usu_ape').val(data.usu_ape);
        $('#usu_usuario').val(data.usu_usuario);
        $('#usu_correo').val(data.usu_correo);
        $('#usu_pass').val('');  /* la contraseña nunca se envía al navegador */
        $('#rol_id').val(data.rol_id).trigger('change');
    });
    $('#modalmantenimiento').modal('show');
}

function eliminar(usu_id) {
    Copeba.confirmar('¿Está seguro de eliminar el registro?', function () {
        Copeba.enviar('../../controller/usuario.php?op=eliminar', { usu_id: usu_id }, 'No se pudo eliminar.')
            .done(function () {
                recargar();
                Copeba.exito('Registro eliminado.');
            });
    }, { peligro: true });
}

$(document).on('click', '#btnnuevo', function () {
    $('#mdltitulo').html('Nuevo Registro');
    $('#usuario_form')[0].reset();
    $('#usu_id').val('');
    $('#modalmantenimiento').modal('show');
});
