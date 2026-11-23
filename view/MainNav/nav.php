<?php
    /* Menú lateral común según el rol. Ocultar opciones es solo comodidad:
       cada vista y cada controlador comprueban el permiso en el servidor. */
    $iconos = array(
        "inicio"   => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        "nuevo"    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        "tickets"  => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M9 6v12" stroke-dasharray="2 2"/>',
        "usuarios" => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.5c1.9.8 3 2.7 3 5.5"/>',
        "perfil"   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        "salir"    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
    );
    $menu = array(
        array("dir" => "Home",            "texto" => "Inicio",       "icono" => "inicio",   "roles" => ROLES_VALIDOS),
        array("dir" => "NuevoTicket",     "texto" => "Nuevo ticket", "icono" => "nuevo",    "roles" => ROLES_VALIDOS),
        array("dir" => "ConsultarTicket", "texto" => "Tickets",      "icono" => "tickets",  "roles" => ROLES_VALIDOS),
        array("dir" => "MntUsuario",      "texto" => "Usuarios",     "icono" => "usuarios", "roles" => array(ROL_ADMIN)),
    );
    /* Carpeta de la pantalla actual, para marcar la opción activa */
    $pantalla_actual = basename(dirname($_SERVER["SCRIPT_NAME"] ?? ""));
    $nombre = trim(($_SESSION["usu_nom"] ?? "")." ".($_SESSION["usu_ape"] ?? ""));
    $iniciales = mb_strtoupper(mb_substr($_SESSION["usu_nom"] ?? "", 0, 1).mb_substr($_SESSION["usu_ape"] ?? "", 0, 1));

    function cp_icono($svg){
        return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$svg.'</svg>';
    }
?>
<aside class="cp-side" id="cp_side" aria-label="Menú principal">
    <a class="cp-side-marca" href="../Home/">
        <span class="cp-side-logo"><img src="../../public/img/copeba/distintivo-copeba.png" alt=""></span>
        <span>
            <strong>Copeba, R.L.</strong>
            <small>Mesa de Ayuda</small>
        </span>
    </a>

    <nav class="cp-side-menu">
        <?php foreach ($menu as $opcion) { if (in_array(rol_actual(), $opcion["roles"], true)) {
            $activa = ($pantalla_actual === $opcion["dir"]); ?>
        <a href="../<?php echo e($opcion["dir"]); ?>/" class="<?php echo $activa ? "activa" : ""; ?>"<?php echo $activa ? ' aria-current="page"' : ""; ?>>
            <?php echo cp_icono($iconos[$opcion["icono"]]); ?>
            <span><?php echo e($opcion["texto"]); ?></span>
        </a>
        <?php } } ?>
    </nav>

    <div class="cp-side-usuario">
        <span class="cp-avatar" aria-hidden="true"><?php echo e($iniciales ?: "?"); ?></span>
        <span class="cp-side-usuario-txt">
            <strong><?php echo e($nombre); ?></strong>
            <small><?php echo e(rol_nombre(rol_actual())); ?></small>
        </span>
        <a href="../MntPerfil/" class="cp-side-accion" title="Perfil" aria-label="Perfil"><?php echo cp_icono($iconos["perfil"]); ?></a>
        <a href="../Logout/logout.php" class="cp-side-accion" title="Cerrar sesión" aria-label="Cerrar sesión"><?php echo cp_icono($iconos["salir"]); ?></a>
    </div>
</aside>
<div class="cp-side-fondo" id="cp_side_fondo"></div>
