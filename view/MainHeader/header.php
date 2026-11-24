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
