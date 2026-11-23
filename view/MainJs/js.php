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

<script src="../../public/js/lib/datatables-net/datatables.min.js"></script>

<script src="../../public/js/lib/bootstrap-sweetalert/sweetalert.min.js"></script>

<script src="../../public/js/lib/summernote/summernote-lite.min.js"></script>

<script src="../../public/js/lib/fancybox/jquery.fancybox.pack.js"></script>

<script src="../../public/js/lib/summernote/summernote-es-ES.min.js"></script>

<script src="../../public/js/lib/select2/select2.full.min.js"></script>