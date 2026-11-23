<header class="site-header">
    <div class="container-fluid">

        <a href="../Home/" class="site-logo" title="Inicio">
            <img class="hidden-md-down" src="../../public/img/copeba/logo-copeba.png" alt="Cooperativa Copeba, R.L." style="height:50px;width:auto;top:-5px">
            <img class="hidden-lg-up" src="../../public/img/copeba/distintivo-copeba.png" alt="Copeba" style="height:40px;width:auto;top:0">
        </a>

        <button id="show-hide-sidebar-toggle" class="show-hide-sidebar">
            <span>toggle menu</span>
        </button>

        <button class="hamburger hamburger--htla">
            <span>toggle menu</span>
        </button>
        
        <div class="site-header-content">
            <div class="site-header-content-in">
                <div class="site-header-shown">
                    <div class="dropdown user-menu">
                        <button class="dropdown-toggle" id="dd-user-menu" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <img src="../../public/<?php echo e(rol_imagen(rol_actual())); ?>" alt="">
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dd-user-menu">
                            <a class="dropdown-item" href="../MntPerfil/"><span class="font-icon glyphicon glyphicon-user"></span>Perfil</a>
                            <a class="dropdown-item" href="#"><span class="font-icon glyphicon glyphicon-question-sign"></span>Ayuda</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../Logout/logout.php"><span class="font-icon glyphicon glyphicon-log-out"></span>Cerrar Sesion</a>
                        </div>
                    </div>
                </div>

                <div class="mobile-menu-right-overlay"></div>

                <!-- Solo para decidir qué mostrar en pantalla; el servidor NO confía en estos valores -->
                <input type="hidden" id="user_idx" value="<?php echo (int)usuario_actual_id(); ?>"><!-- ID del Usuario-->
                <input type="hidden" id="rol_idx" value="<?php echo (int)rol_actual(); ?>"><!-- Rol del Usuario-->

                <div class="dropdown dropdown-typical">
                    <a href="#" class="dropdown-toggle no-arr">
                        <span class="font-icon font-icon-user"></span>
                        <span class="lblcontactonomx"><?php echo e($_SESSION["usu_nom"] ?? ""); ?> <?php echo e($_SESSION["usu_ape"] ?? ""); ?></span>
                    </a>
                </div>

            </div>
        </div>
    </div>
</header>