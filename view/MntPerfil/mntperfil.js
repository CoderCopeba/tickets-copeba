$(document).on('click', '#btnactualizar', function () {
    var actual = $('#txtpassactual').val();
    var pass = $('#txtpass').val();
    var nueva = $('#txtpassnew').val();

    if (!actual || !pass || !nueva) {
        Copeba.aviso('Completa los tres campos.');
        return;
    }
    if (pass !== nueva) {
        Copeba.aviso('Las contraseñas nuevas no coinciden.');
        return;
    }
    /* El id del usuario lo toma el servidor de la sesión */
    Copeba.enviar('../../controller/usuario.php?op=password', { usu_pass_actual: actual, usu_pass: nueva }, 'No se pudo actualizar.')
        .done(function () {
            $('#txtpassactual, #txtpass, #txtpassnew').val('');
            Copeba.exito('Contraseña actualizada.');
        });
});
