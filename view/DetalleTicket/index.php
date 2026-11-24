<?php
    require_once("../../config/conexion.php");
    require_once("../../models/Ticket.php");
    requiere_sesion("vista");
    /* Un Usuario (rol 1) solo puede abrir SUS tickets; si no, 403 */
    $tick_id = requiere_acceso_ticket(new Ticket(), $_GET["ID"] ?? 0, "vista");
    $soporte = es_soporte();
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Ticket #<?php echo (int)$tick_id; ?></title>
</head>
<body class="with-side-menu" data-ticket="<?php echo (int)$tick_id; ?>">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">

            <header class="cp-encabezado cp-encabezado-ticket">
                <div class="cp-encabezado-izq">
                    <a href="../ConsultarTicket/" class="cp-btn cp-btn-borde cp-btn-chico" id="btn_volver"><?php echo icono("volver", 14, 2.2); ?> Volver</a>
                    <div>
                        <div class="cp-ticket-meta">
                            <span class="cp-mono" id="lbl_folio"></span>
                            <span id="lbl_estado"></span>
                        </div>
                        <h1 id="lbl_titulo">Cargando…</h1>
                    </div>
                </div>
                <div class="cp-encabezado-acciones" id="acciones"></div>
            </header>

            <div class="cp-rejilla cp-rejilla-form">
                <div class="cp-columna">

                    <!-- Solicitud original -->
                    <section class="cp-tarjeta cp-panel" id="solicitud">
                        <div class="cp-solicitante">
                            <span class="cp-avatar" id="lbl_iniciales"></span>
                            <div>
                                <strong id="lbl_solicitante"></strong>
                                <small id="lbl_reportado"></small>
                            </div>
                        </div>
                        <div class="cp-contenido" id="lbl_descripcion"></div>
                        <div class="cp-docs" id="docs_ticket"></div>
                    </section>

                    <!-- Bitácora y respuesta -->
                    <section class="cp-tarjeta cp-panel">
                        <h2 class="cp-panel-titulo">Bitácora de seguimiento</h2>
                        <div class="cp-bitacora" id="bitacora"></div>

                        <form class="cp-responder" id="frm_responder" novalidate>
                            <?php if ($soporte) { ?>
                            <div class="cp-pestanas" role="tablist">
                                <button type="button" class="activa" data-tipo="respuesta" role="tab" aria-selected="true">Responder al usuario</button>
                                <button type="button" data-tipo="nota" role="tab" aria-selected="false"><?php echo icono("candado", 13, 2.2); ?> Nota interna</button>
                            </div>
                            <?php } ?>
                            <textarea id="tickd_descrip" name="tickd_descrip"></textarea>
                            <p class="cp-nota-aviso" id="aviso_nota" hidden>Las notas internas solo las ve el equipo de TI.</p>
                            <input type="file" id="adjuntos_respuesta" class="cp-oculto" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt">
                            <ul class="cp-archivos" id="lista_archivos"></ul>
                            <div class="cp-responder-pie">
                                <label for="adjuntos_respuesta" class="cp-btn cp-btn-borde cp-btn-chico"><?php echo icono("clip", 14, 2.2); ?> Adjuntar archivo</label>
                                <button type="submit" class="cp-btn cp-btn-azul" id="btn_enviar"><?php echo icono("enviar", 14, 2.2); ?> <span>Enviar respuesta</span></button>
                            </div>
                        </form>
                        <p class="cp-cerrado-aviso" id="aviso_cerrado" hidden>Este ticket está cerrado. Reábrelo para agregar respuestas.</p>
                    </section>
                </div>

                <aside class="cp-columna">
                    <section class="cp-tarjeta cp-panel">
                        <h2 class="cp-panel-titulo cp-con-linea">Ficha del ticket</h2>
                        <dl class="cp-ficha">
                            <dt>Categoría</dt>        <dd id="f_categoria"></dd>
                            <dt>Prioridad / SLA</dt>  <dd id="f_prioridad"></dd>
                            <dt>Técnico asignado</dt> <dd id="f_asignado"></dd>
                            <dt>Estado</dt>           <dd id="f_estado"></dd>
                            <dt>Creado</dt>           <dd id="f_creado" class="cp-mono"></dd>
                            <dt id="f_cierre_t" hidden>Cerrado</dt><dd id="f_cierre" class="cp-mono" hidden></dd>
                        </dl>
                        <!-- "Equipo afectado" y "Repuesto de bodega" se mostrarán cuando exista Inventario -->
                    </section>
                </aside>
            </div>

        </div>
    </div>

    <?php require_once("../MainJs/js.php");?>
    <script src="detalleticket.js?v=<?php echo filemtime(__DIR__."/detalleticket.js"); ?>"></script>
</body>
</html>
