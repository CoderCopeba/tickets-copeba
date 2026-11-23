<!-- Barra superior: solo visible en celular/tableta (en computadora el menú lateral está fijo) -->
<header class="cp-topbar">
    <button type="button" class="cp-topbar-menu" id="cp_btn_menu" aria-controls="cp_side" aria-expanded="false" aria-label="Abrir menú">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <a href="../Home/" class="cp-topbar-logo"><img src="../../public/img/copeba/logo-copeba.png" alt="Cooperativa Copeba, R.L."></a>
</header>

<!-- Solo para decidir qué mostrar en pantalla; el servidor NO confía en estos valores -->
<input type="hidden" id="user_idx" value="<?php echo (int)usuario_actual_id(); ?>">
<input type="hidden" id="rol_idx" value="<?php echo (int)rol_actual(); ?>">
