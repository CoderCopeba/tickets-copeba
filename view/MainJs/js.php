<script src="../../public/js/lib/jquery/jquery-3.7.1.min.js"></script>
<!-- Compatibilidad con plugins antiguos de la plantilla (fancybox, bootstrap alpha) -->
<script src="../../public/js/lib/jquery/jquery-migrate-3.5.2.min.js"></script>
<script>
/* Envía el token CSRF en todas las peticiones AJAX (el servidor lo exige en los POST) */
$.ajaxSetup({ headers: { "X-CSRF-Token": $('meta[name="csrf-token"]').attr("content") } });
</script>
<script src="../../public/js/lib/tether/tether.min.js"></script>
<script src="../../public/js/lib/bootstrap/bootstrap.min.js"></script>
<script src="../../public/js/plugins.js"></script>
<script src="../../public/js/app.js"></script>

<script src="../../public/js/lib/bootstrap-sweetalert/sweetalert.min.js"></script>

<script src="../../public/js/lib/summernote/summernote-lite.min.js"></script>

<script src="../../public/js/lib/fancybox/jquery.fancybox.pack.js"></script>

<script src="../../public/js/lib/summernote/summernote-es-ES.min.js"></script>

<script src="../../public/js/lib/select2/select2.full.min.js"></script>

<!-- Recursos compartidos (alertas, tablas, editor, peticiones): public/js/copeba.js -->
<script src="../../public/js/copeba.js?v=<?php echo filemtime(__DIR__."/../../public/js/copeba.js"); ?>"></script>
<script src="../../public/js/copeba-listado.js?v=<?php echo filemtime(__DIR__."/../../public/js/copeba-listado.js"); ?>"></script>
<script>
/* Menú lateral en celular: abrir / cerrar */
(function () {
    var btn = document.getElementById('cp_btn_menu');
    var fondo = document.getElementById('cp_side_fondo');
    if (!btn) { return; }
    function cerrar() { document.body.classList.remove('cp-menu-abierto'); btn.setAttribute('aria-expanded', 'false'); }
    btn.addEventListener('click', function () {
        var abierto = document.body.classList.toggle('cp-menu-abierto');
        btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    if (fondo) { fondo.addEventListener('click', cerrar); }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cerrar(); } });
})();
</script>

<script>
/* Salir de "Ver como" */
$('#cp_btn_volver').on('click', function () {
    Copeba.enviar('../../controller/cuenta.php?op=volver', {}, 'No se pudo volver a tu cuenta.')
        .done(function () { location.href = '../MntUsuario/'; });
});
</script>
