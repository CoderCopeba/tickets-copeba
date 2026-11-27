<?php
    require_once("../../config/conexion.php");
    requiere_sesion("vista");
    $soporte = es_soporte();
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Tickets</title>
</head>
<body class="with-side-menu">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">
            <header class="cp-encabezado">
                <div>
                    <small>Sistema Interno · Cooperativa Copeba, R.L.</small>
                    <h1>Tickets</h1>
                </div>
                <p class="cp-encabezado-nota"><?php echo $soporte ? "Todas las solicitudes de soporte" : "Las solicitudes que has reportado"; ?></p>
            </header>

            <div id="lista_tickets"></div>
        </div>
    </div>

    <?php if ($soporte) { ?>
    <!-- Asignar a un técnico (el servidor vuelve a comprobar el permiso) -->
    <dialog class="cp-dialogo" id="dlg_asignar" aria-labelledby="dlg_asignar_titulo">
        <form id="asignar_form" method="dialog" novalidate>
            <header><h2 id="dlg_asignar_titulo">Asignar ticket</h2><p id="dlg_asignar_ticket"></p></header>
            <div class="cp-dialogo-cuerpo">
                <input type="hidden" id="tick_id" name="tick_id">
                <div class="cp-campo">
                    <label for="usu_asig">Técnico</label>
                    <select class="cp-control" id="usu_asig" name="usu_asig" required></select>
                </div>
            </div>
            <footer>
                <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cancelar</button>
                <button type="submit" class="cp-btn cp-btn-primario">Asignar</button>
            </footer>
        </form>
    </dialog>
    <?php } ?>

    <?php require_once("../MainJs/js.php");?>
    <script src="consultarticket.js?v=<?php echo filemtime(__DIR__."/consultarticket.js"); ?>"></script>
</body>
</html>
