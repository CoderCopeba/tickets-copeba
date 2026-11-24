<?php
    /* Menú lateral común según el rol. Ocultar opciones es solo comodidad:
       cada vista y cada controlador comprueban el permiso en el servidor. */
    $menu = array(
        array("dir" => "Home",            "texto" => "Inicio",       "icono" => "inicio",   "roles" => ROLES_VALIDOS),
        array("dir" => "NuevoTicket",     "texto" => "Nuevo ticket", "icono" => "nuevo",    "roles" => ROLES_VALIDOS),
        array("dir" => "ConsultarTicket", "texto" => "Tickets",      "icono" => "ticket",  "roles" => ROLES_VALIDOS),
        array("dir" => "ControlUsuarios", "texto" => "Cuentas",      "icono" => "llave",    "soporte" => true),
        array("dir" => "MntUsuario",      "texto" => "Usuarios",     "icono" => "usuarios", "roles" => array(ROL_ADMIN)),
        array("dir" => "MntArea",         "texto" => "Áreas y sedes", "icono" => "areas",    "roles" => array(ROL_ADMIN)),
    );
    /* Carpeta de la pantalla actual, para marcar la opción activa */
    $pantalla_actual = basename(dirname($_SERVER["SCRIPT_NAME"] ?? ""));
    $nombre = trim(($_SESSION["usu_nom"] ?? "")." ".($_SESSION["usu_ape"] ?? ""));
    $iniciales = mb_strtoupper(mb_substr($_SESSION["usu_nom"] ?? "", 0, 1).mb_substr($_SESSION["usu_ape"] ?? "", 0, 1));
    /* Debajo del nombre: "Puesto · Área" (o el tipo de cuenta si aún no tiene área) */
    require_once(__DIR__."/../../models/Area.php");
    $subtitulo = (new Area())->resumen_usuario(usuario_actual_id());
    if (es_admin()) { $subtitulo = "Administrador del sistema"; }
    if ($subtitulo === "") { $subtitulo = rol_nombre(rol_actual()); }

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
        <?php foreach ($menu as $opcion) { if (isset($opcion["soporte"]) ? es_soporte() : in_array(rol_actual(), $opcion["roles"], true)) {
            $activa = ($pantalla_actual === $opcion["dir"]); ?>
        <a href="../<?php echo e($opcion["dir"]); ?>/" class="<?php echo $activa ? "activa" : ""; ?>"<?php echo $activa ? ' aria-current="page"' : ""; ?>>
            <?php echo icono($opcion["icono"]); ?>
            <span><?php echo e($opcion["texto"]); ?></span>
        </a>
        <?php } } ?>
    </nav>

    <div class="cp-side-usuario">
        <span class="cp-avatar" aria-hidden="true"><?php echo e($iniciales ?: "?"); ?></span>
        <span class="cp-side-usuario-txt">
            <strong><?php echo e($nombre); ?></strong>
            <small><?php echo e($subtitulo); ?></small>
        </span>
        <a href="../MntPerfil/" class="cp-side-accion" title="Perfil" aria-label="Perfil"><?php echo icono("perfil"); ?></a>
        <a href="../Logout/logout.php" class="cp-side-accion" title="Cerrar sesión" aria-label="Cerrar sesión"><?php echo icono("salir"); ?></a>
    </div>
</aside>
<div class="cp-side-fondo" id="cp_side_fondo"></div>
