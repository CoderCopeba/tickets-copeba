<?php
    require_once("../../config/conexion.php");
    requiere_soporte("vista");   /* Informática (y administradores del sistema) */
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Control de cuentas</title>
</head>
<body class="with-side-menu">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">

            <header class="cp-encabezado">
                <div>
                    <small>Informática · Copeba, R.L.</small>
                    <h1>Control de cuentas</h1>
                </div>
                <p class="cp-encabezado-nota">Restablece accesos con contraseñas temporales. Nadie, ni Informática, puede ver las contraseñas.</p>
            </header>

            <div class="cp-kpis" id="resumen"></div>

            <div class="cp-seccion-titulo">
                <div>
                    <h2>Cuentas</h2>
                    <p>Si alguien olvidó su contraseña, genera una temporal y díctasela: al entrar deberá crear la suya.</p>
                </div>
                <input type="search" class="cp-control cp-buscar" id="buscar" placeholder="Buscar por nombre, usuario, área o sede…" aria-label="Buscar cuenta">
            </div>

            <div class="cp-tarjeta cp-tabla-caja">
                <table class="cp-tabla">
                    <thead>
                        <tr><th>Persona</th><th>Usuario</th><th>Área · Sede</th><th>Contraseña</th><th>Último movimiento</th><th class="cp-der">Acciones</th></tr>
                    </thead>
                    <tbody id="cuentas"><tr><td colspan="6" class="cp-vacio">Cargando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Contraseña temporal recién generada (se muestra una sola vez) -->
    <dialog class="cp-dialogo" id="dlg_temporal" aria-labelledby="dlg_temporal_titulo">
        <header><h2 id="dlg_temporal_titulo">Contraseña temporal</h2><p id="dlg_temporal_para"></p></header>
        <div class="cp-dialogo-cuerpo">
            <div class="cp-temporal">
                <code id="txt_temporal"></code>
                <button type="button" class="cp-btn cp-btn-borde cp-btn-chico" id="btn_copiar">Copiar</button>
            </div>
            <ul class="cp-lista-check cp-temporal-pasos">
                <li><?php echo icono("cerrado", 15, 2); ?> <span>Díctala por teléfono o entrégala en persona; <b>no la envíes por correo</b>.</span></li>
                <li><?php echo icono("cerrado", 15, 2); ?> <span>Vence en <b id="txt_horas">24</b> horas y solo sirve para entrar una vez.</span></li>
                <li><?php echo icono("cerrado", 15, 2); ?> <span>Al entrar, el sistema le pedirá crear su propia contraseña (escribirla dos veces).</span></li>
                <li><?php echo icono("cerrado", 15, 2); ?> <span>Esta ventana es la única vez que se muestra. Si se pierde, genera otra.</span></li>
            </ul>
        </div>
        <footer><button type="button" class="cp-btn cp-btn-primario" data-cerrar>Listo</button></footer>
    </dialog>

    <!-- Historial de una cuenta -->
    <dialog class="cp-dialogo" id="dlg_historial" aria-labelledby="dlg_historial_titulo">
        <header><h2 id="dlg_historial_titulo">Historial</h2></header>
        <div class="cp-dialogo-cuerpo"><ul class="cp-membresias" id="historial"></ul></div>
        <footer><button type="button" class="cp-btn cp-btn-primario" data-cerrar>Cerrar</button></footer>
    </dialog>

    <?php require_once("../MainJs/js.php");?>
    <script src="controlusuarios.js?v=<?php echo filemtime(__DIR__."/controlusuarios.js"); ?>"></script>
</body>
</html>
