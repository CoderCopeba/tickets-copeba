<!-- Barra superior: solo visible en celular/tableta (en computadora el menú lateral está fijo) -->
<header class="cp-topbar">
    <button type="button" class="cp-topbar-menu" id="cp_btn_menu" aria-controls="cp_side" aria-expanded="false" aria-label="Abrir menú">
        <?php echo icono("menu", 22, 2); ?>
    </button>
    <a href="../Home/" class="cp-topbar-logo"><img src="../../public/img/copeba/logo-copeba.png" alt="Cooperativa Copeba, R.L."></a>
</header>

<!-- Solo para decidir qué mostrar en pantalla; el servidor NO confía en estos valores -->
<input type="hidden" id="user_idx" value="<?php echo (int)usuario_actual_id(); ?>">
<input type="hidden" id="soporte_idx" value="<?php echo es_soporte() ? 1 : 0; ?>">
<input type="hidden" id="admin_idx" value="<?php echo (es_admin() && !viendo_como()) ? 1 : 0; ?>">

<?php if (viendo_como()) { ?>
<!-- "Ver como": marco verde en toda la pantalla y aviso pequeño con la salida -->
<div class="cp-vercomo-marco" aria-hidden="true"></div>
<div class="cp-vercomo" role="status">
    <span>
        <?php echo icono("perfil", 15, 2); ?>
        Viendo como <strong><?php echo e(trim(($_SESSION["usu_nom"] ?? "")." ".($_SESSION["usu_ape"] ?? ""))); ?></strong>
        <small>Solo lectura · tú eres <?php echo e($_SESSION["ver_como_nombre"] ?? ""); ?></small>
    </span>
    <button type="button" class="cp-btn cp-btn-chico" id="cp_btn_volver">Volver a mi cuenta</button>
</div>
<?php } ?>
