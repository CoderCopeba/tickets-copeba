<?php
    require_once("../../config/conexion.php");
    requiere_sesion("vista");
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Tareas</title>
</head>
<body class="with-side-menu">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">
            <header class="cp-encabezado">
                <div>
                    <small>Sistema Interno · Cooperativa Copeba, R.L.</small>
                    <h1>Tareas</h1>
                </div>
                <p class="cp-encabezado-nota">Trabajo que asigna la jefatura de cada área</p>
            </header>

            <!-- Solo se muestra si la persona dirige algún área (el servidor lo decide) -->
            <div id="lista_asignadas" hidden></div>
            <div id="lista_mias"></div>
        </div>
    </div>

    <!-- Nueva tarea (el servidor vuelve a comprobar que diriges el área y a sus personas) -->
    <dialog class="cp-dialogo cp-dialogo-ancho" id="dlg_nueva" aria-labelledby="dlg_nueva_titulo">
        <form id="nueva_form" method="dialog" novalidate>
            <header><h2 id="dlg_nueva_titulo">Nueva tarea</h2><p id="dlg_nueva_nota">Se asigna de una vez a todas las personas que marques; cada una hace su propia entrega.</p></header>
            <div class="cp-dialogo-cuerpo">
                <div class="cp-campos-2">
                    <div class="cp-campo" id="campo_grupo">
                        <label for="grupo">Área</label>
                        <select class="cp-control" id="grupo" required></select>
                    </div>
                    <!-- Al editar, el área no cambia -->
                    <div class="cp-campo" id="campo_grupo_fijo" hidden>
                        <span class="cp-etiqueta">Área</span>
                        <p class="cp-control" id="grupo_fijo" style="margin:0"></p>
                    </div>
                    <div class="cp-campo">
                        <label for="prio_id">Urgencia</label>
                        <select class="cp-control" id="prio_id" name="prio_id" required></select>
                    </div>
                </div>
                <div class="cp-campo">
                    <span class="cp-etiqueta">Asignar a</span>
                    <div class="cp-tarea-personas" id="personas" aria-live="polite"></div>
                </div>
                <div class="cp-campo">
                    <label for="titulo">Título</label>
                    <input type="text" class="cp-control" id="titulo" name="titulo" maxlength="200" required>
                </div>
                <div class="cp-campo">
                    <label for="descrip">Instrucciones</label>
                    <textarea id="descrip" name="descrip"></textarea>
                </div>
                <div class="cp-campo">
                    <label for="fech_limite">Fecha límite (opcional)</label>
                    <input type="date" id="fech_limite" name="fech_limite">
                </div>
            </div>
            <footer>
                <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cancelar</button>
                <button type="submit" class="cp-btn cp-btn-primario" id="nueva_enviar">Asignar tarea</button>
            </footer>
        </form>
    </dialog>

    <!-- Detalle de una tarea: se llena desde tareas.js -->
    <dialog class="cp-dialogo cp-dialogo-ancho" id="dlg_detalle" aria-labelledby="det_titulo">
        <header><h2 id="det_titulo"></h2><p id="det_meta"></p></header>
        <div class="cp-dialogo-cuerpo">
            <div class="cp-tarea-instrucciones" id="det_instrucciones"></div>
            <div id="det_cuerpo"></div>
        </div>
        <footer id="det_pie">
            <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cerrar</button>
        </footer>
    </dialog>

    <?php require_once("../MainJs/js.php");?>
    <script src="tareas.js?v=<?php echo filemtime(__DIR__."/tareas.js"); ?>"></script>
</body>
</html>
