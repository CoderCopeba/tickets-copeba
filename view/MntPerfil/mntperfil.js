

$(document).on("click","#btnactualizar", function(){
    var actual = $("#txtpassactual").val();
    var pass = $("#txtpass").val();
    var newpass = $("#txtpassnew").val();

    if (actual.length == 0 || pass.length == 0 || newpass.length == 0) {
        swal("Error!", "Campos Vacios", "error");
    }else{
        if (pass==newpass){
            /* El id del usuario lo toma el servidor de la sesión */
            $.post("../../controller/usuario.php?op=password", {usu_pass_actual:actual, usu_pass:newpass}, function (data) {
                var resp;
                try { resp = JSON.parse(data); } catch (err) { resp = null; }
                if (resp && resp.status === "ok"){
                    $("#txtpassactual, #txtpass, #txtpassnew").val('');
                    swal("Correcto!", "Actualizado Correctamente", "success");
                }else{
                    swal("Error!", (resp && resp.message) ? resp.message : "No se pudo actualizar.", "error");
                }
            });

        }else{
            swal("Error!", "Las contraseñas no coinciden", "error");
        }
    }
});
