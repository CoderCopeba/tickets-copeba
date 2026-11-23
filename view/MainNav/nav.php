<?php
    /* Menú lateral según el rol (Fase 3). Ocultar opciones es solo comodidad:
       cada vista y cada controlador comprueban el permiso en el servidor. */
    $menu = array(
        array("url" => "../Home/",            "texto" => "Inicio",                "roles" => ROLES_VALIDOS),
        array("url" => "../NuevoTicket/",     "texto" => "Nuevo Ticket",          "roles" => ROLES_VALIDOS),
        array("url" => "../ConsultarTicket/", "texto" => "Consultar Ticket",      "roles" => ROLES_VALIDOS),
        array("url" => "../MntUsuario/",      "texto" => "Mantenimiento Usuario", "roles" => array(ROL_ADMIN)),
    );
?>
<nav class="side-menu">
    <ul class="side-menu-list">
        <?php foreach ($menu as $opcion) { if (in_array(rol_actual(), $opcion["roles"], true)) { ?>
        <li class="blue-dirty">
            <a href="<?php echo e($opcion["url"]); ?>">
                <span class="glyphicon glyphicon-th"></span>
                <span class="lbl"><?php echo e($opcion["texto"]); ?></span>
            </a>
        </li>
        <?php } } ?>
    </ul>
</nav>
