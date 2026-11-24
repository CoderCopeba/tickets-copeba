<?php
    require_once("../../config/conexion.php");
    requiere_sesion("vista");
    require_once("../../models/Categoria.php");
    require_once("../../models/Prioridad.php");
    $categorias  = (new Categoria())->get_categoria();
    $prioridades = (new Prioridad())->listar();
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Nuevo ticket</title>
</head>
<body class="with-side-menu">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">

            <header class="cp-encabezado">
                <div>
                    <small>Mesa de Ayuda · Copeba, R.L.</small>
                    <h1>Nuevo ticket</h1>
                </div>
                <p class="cp-encabezado-nota">Desde esta ventana podrá generar nuevas solicitudes de soporte</p>
            </header>

            <div class="cp-rejilla cp-rejilla-form">
                <form class="cp-tarjeta cp-panel" id="ticket_form" method="post" novalidate>
                    <div class="cp-seccion-titulo cp-con-linea">
                        <div>
                            <h2>Ingresar información del ticket</h2>
                            <p>Completa los campos para asignar tu solicitud al equipo técnico correspondiente.</p>
                        </div>
                    </div>

                    <!-- El creador del ticket lo toma el servidor de la sesión -->
                    <div class="cp-campo">
                        <label for="tick_titulo">Título del problema o solicitud</label>
                        <input type="text" class="cp-control" id="tick_titulo" name="tick_titulo" maxlength="250"
                               placeholder="Ej. Error al generar reporte mensual o solicitud de tóner" required>
                    </div>

                    <div class="cp-campos-2">
                        <div class="cp-campo">
                            <label for="cat_id">Categoría</label>
                            <select class="cp-control" id="cat_id" name="cat_id" required>
                                <?php foreach ($categorias as $c) { ?>
                                <option value="<?php echo (int)$c["cat_id"]; ?>"><?php echo e($c["cat_nom"]); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="cp-campo">
                            <label for="prio_id">Urgencia</label>
                            <select class="cp-control" id="prio_id" name="prio_id">
                                <?php foreach ($prioridades as $p) { ?>
                                <option value="<?php echo (int)$p["prio_id"]; ?>"<?php echo (int)$p["prio_id"] === Prioridad::NORMAL ? " selected" : ""; ?>>
                                    <?php echo e($p["prio_nom"]." (".$p["prio_descrip"].")"); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="cp-campo">
                        <label for="tick_descrip">Descripción</label>
                        <textarea id="tick_descrip" name="tick_descrip"></textarea>
                    </div>

                    <div class="cp-campo">
                        <span class="cp-etiqueta">Documentos adicionales</span>
                        <label class="cp-adjuntos" for="fileElem">
                            <span class="cp-adjuntos-ico"><?php echo icono("adjunto", 20, 2); ?></span>
                            <span class="cp-adjuntos-txt">
                                <strong>Adjuntar capturas o documentos</strong>
                                <small>Permitidos: pdf, jpg, png, doc, docx, xls, xlsx, txt · Máx. 10 MB</small>
                            </span>
                            <span class="cp-btn cp-btn-borde">Elegir archivos</span>
                        </label>
                        <input type="file" id="fileElem" name="fileElem" class="cp-oculto" multiple
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt">
                        <ul class="cp-archivos" id="lista_archivos" aria-live="polite"></ul>
                    </div>

                    <div class="cp-acciones">
                        <button type="button" class="cp-btn cp-btn-borde" id="btn_cancelar">Cancelar</button>
                        <button type="submit" class="cp-btn cp-btn-primario" id="btn_guardar">
                            <?php echo icono("guardar", 16, 2); ?> Guardar ticket
                        </button>
                    </div>
                </form>

                <aside>
                    <!-- "Tu estación asignada" se mostrará aquí cuando exista el módulo de Inventario -->
                    <section class="cp-tarjeta cp-panel cp-panel-suave">
                        <h2 class="cp-panel-titulo">Antes de enviar</h2>
                        <ul class="cp-lista-check">
                            <li><?php echo icono("cerrado", 15, 2); ?> <span>Si ves un mensaje de error, adjunta una captura de pantalla completa.</span></li>
                            <li><?php echo icono("cerrado", 15, 2); ?> <span>Indica tu número de extensión o agencia si requieres visita presencial.</span></li>
                            <li><?php echo icono("cerrado", 15, 2); ?> <span>Para insumos como tóner o mouse, selecciona <b>Petición de Servicio</b>.</span></li>
                            <li><?php echo icono("cerrado", 15, 2); ?> <span>Usa urgencia <b>Crítica</b> solo si se detiene la atención a asociados.</span></li>
                        </ul>
                    </section>
                </aside>
            </div>

        </div>
    </div>

    <?php require_once("../MainJs/js.php");?>
    <script src="nuevoticket.js?v=<?php echo filemtime(__DIR__."/nuevoticket.js"); ?>"></script>
</body>
</html>
