
function init(){
   
    $("#ticket_form").on("submit",function(e){
        guardaryeditar(e);	
    });
    
}

$(document).ready(function() {
    $('#tick_descrip').summernote({
        height: 150,
        lang: "es-ES",
        callbacks: {
            onImageUpload: function(image) {
                console.log("Image detect...");
                myimagetreat(image[0]);
            },
            onPaste: function (e) {
                console.log("Text detect...");
            }
        },
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough', 'superscript', 'subscript']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['height', ['height']]
        ]
    });

    $.post("../../controller/categoria.php?op=combo",function(data, status){
        $('#cat_id').html(data);
    });

});

function guardaryeditar(e){
    e.preventDefault();
    var formData = new FormData($("#ticket_form")[0]);
    if ($('#tick_descrip').summernote('isEmpty') || $('#tick_titulo').val()==''){
        swal("Advertencia!", "Campos Vacios", "warning");
    }else{
        var archivos = $('#fileElem')[0].files;
        var totalfiles = archivos.length;
        var maxBytes = 10 * 1024 * 1024;
        var permitidas = ['pdf','jpg','jpeg','png','doc','docx','xls','xlsx','txt'];
        for (var i = 0; i < totalfiles; i++) {
            var ext = archivos[i].name.split('.').pop().toLowerCase();
            if (archivos[i].name.indexOf('.') === -1 || permitidas.indexOf(ext) === -1) {
                swal("Advertencia!", "Tipo de archivo no permitido: " + archivos[i].name + ". Permitidos: " + permitidas.join(', '), "warning");
                return;
            }
            if (archivos[i].size > maxBytes) {
                swal("Advertencia!", "El archivo " + archivos[i].name + " supera el máximo de 10 MB.", "warning");
                return;
            }
        }
        /* El input se llama "fileElem"; se quita para no enviarlo duplicado y se envían como files[] */
        formData.delete("fileElem");
        for (var j = 0; j < totalfiles; j++) {
            formData.append("files[]", archivos[j]);
        }

        $.ajax({
            url: "../../controller/ticket.php?op=insert",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function(data){
                data = JSON.parse(data);
                if (data && data.error){
                    swal("Advertencia!", data.error, "warning");
                    return;
                }
                if (!Array.isArray(data) || data.length === 0){
                    swal("Error!", "No se pudo registrar el ticket.", "error");
                    return;
                }
                console.log(data[0].tick_id);

                $.post("../../controller/email.php?op=ticket_abierto", {tick_id : data[0].tick_id}, function (data) {

                });

                $('#tick_titulo').val('');
                $('#tick_descrip').summernote('reset');
                swal("Correcto!", "Registrado Correctamente", "success");
            }
        });
    }
}

init();