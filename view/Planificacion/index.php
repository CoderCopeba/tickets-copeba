<?php
    require_once("../../config/conexion.php");
    requiere_sesion("vista");
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Planificación</title>
</head>
<body class="with-side-menu">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">
            <header class="cp-encabezado">
                <div>
                    <small>Mesa de Ayuda · Copeba, R.L.</small>
                    <h1>Planificación</h1>
                </div>
                <p class="cp-encabezado-nota">Lo que planeas hacer en la semana o el mes, y cómo te fue</p>
            </header>

            <!-- Periodo: semanal / mensual y navegación -->
            <div class="cp-plan-periodo">
                <div class="cp-plan-tipo" role="tablist" aria-label="Tipo de plan">
                    <button type="button" role="tab" data-tipo="semana" class="activa" aria-selected="true">Semanal</button>
                    <button type="button" role="tab" data-tipo="mes" aria-selected="false">Mensual</button>
                </div>
                <div class="cp-plan-nav">
                    <button type="button" class="cp-cal-flecha" id="btn_anterior" aria-label="Periodo anterior">‹</button>
                    <strong id="lbl_periodo">…</strong>
                    <button type="button" class="cp-cal-flecha" id="btn_siguiente" aria-label="Periodo siguiente">›</button>
                    <button type="button" class="cp-btn cp-btn-borde cp-btn-chico" id="btn_actual">Actual</button>
                    <button type="button" class="cp-btn cp-btn-borde cp-btn-chico" id="btn_proximo">Próximo</button>
                </div>
            </div>

            <section class="cp-tarjeta cp-plan" id="mi_plan">
                <div class="cp-plan-cab">
                    <div>
                        <h2>Mi plan</h2>
                        <p id="lbl_plazo"></p>
                    </div>
                    <div class="cp-plan-cab-der"><span id="lbl_estado"></span><span id="lugar_entregar"></span></div>
                </div>
                <div id="comentario_jefe"></div>
                <ul class="cp-plan-lista" id="actividades"></ul>
                <form class="cp-plan-nueva" id="nueva_form" novalidate>
                    <input type="text" class="cp-control" id="nueva_texto" maxlength="300" autocomplete="off" placeholder="Nueva actividad, p. ej. Cambio de batería laptop de Caja Soloma" aria-label="Nueva actividad">
                    <input type="date" id="nueva_dia" aria-label="Día (opcional)">
                    <button type="submit" class="cp-btn cp-btn-borde">Agregar</button>
                    <button type="button" class="cp-btn cp-btn-borde" id="btn_desde_ticket" hidden>Desde un ticket</button>
                </form>
                <div class="cp-plan-pie" id="pie_plan"></div>
            </section>

            <!-- Solo si la persona dirige algún área (el servidor lo decide) -->
            <div id="lista_equipo" hidden></div>
        </div>
    </div>

    <!-- No cumplido / Posponer: siempre con justificación -->
    <dialog class="cp-dialogo" id="dlg_justificar" aria-labelledby="just_titulo">
        <form id="just_form" method="dialog" novalidate>
            <header><h2 id="just_titulo"></h2><p id="just_actividad"></p></header>
            <div class="cp-dialogo-cuerpo">
                <div class="cp-campo">
                    <label for="just_texto">Justificación</label>
                    <textarea class="cp-control cp-tarea-texto" id="just_texto" maxlength="1000" required
                              placeholder="P. ej. La batería de repuesto no ha llegado"></textarea>
                </div>
                <div class="cp-campo" id="campo_nueva_fecha">
                    <label for="just_fecha">Nueva fecha</label>
                    <input type="date" id="just_fecha">
                    <small class="cp-suave">La actividad pasa sola al plan del periodo de esa fecha.</small>
                </div>
            </div>
            <footer>
                <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cancelar</button>
                <button type="submit" class="cp-btn cp-btn-primario" id="just_boton">Guardar</button>
            </footer>
        </form>
    </dialog>

    <!-- Traer un ticket al plan (solo quien atiende tickets; el servidor lo comprueba) -->
    <dialog class="cp-dialogo cp-dialogo-ancho" id="dlg_ticket" aria-labelledby="tk_titulo">
        <form id="ticket_form" method="dialog" novalidate>
            <header><h2 id="tk_titulo">Planificar un ticket</h2><p>Tickets abiertos tuyos o sin técnico. Si no tiene técnico, lo tomas tú.</p></header>
            <div class="cp-dialogo-cuerpo">
                <input type="search" class="cp-control" id="tk_buscar" placeholder="Buscar por folio, título, agencia o solicitante…" aria-label="Buscar ticket" autocomplete="off">
                <div class="cp-plan-tickets" id="tk_lista" role="radiogroup" aria-label="Tickets"></div>
                <div class="cp-campos-2">
                    <div class="cp-campo">
                        <label for="tk_texto">Actividad (opcional)</label>
                        <input type="text" class="cp-control" id="tk_texto" maxlength="300" autocomplete="off" placeholder="P. ej. Viaje a Soloma">
                    </div>
                    <div class="cp-campo">
                        <label for="tk_dia">Día (opcional)</label>
                        <input type="date" id="tk_dia">
                    </div>
                </div>
            </div>
            <footer>
                <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cancelar</button>
                <button type="submit" class="cp-btn cp-btn-primario">Agregar al plan</button>
            </footer>
        </form>
    </dialog>

    <!-- Jefatura: plan de una persona -->
    <dialog class="cp-dialogo cp-dialogo-ancho" id="dlg_plan" aria-labelledby="ver_titulo">
        <header><h2 id="ver_titulo"></h2><p id="ver_meta"></p></header>
        <div class="cp-dialogo-cuerpo">
            <ul class="cp-plan-lista" id="ver_actividades"></ul>
            <div class="cp-campo" id="campo_comentario">
                <label for="ver_comentario">Comentario para la persona (opcional)</label>
                <textarea class="cp-control cp-tarea-texto" id="ver_comentario" maxlength="1000"></textarea>
            </div>
        </div>
        <footer>
            <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cerrar</button>
            <a class="cp-btn cp-btn-borde" id="btn_pdf_ver" target="_blank" rel="noopener">Descargar PDF</a>
            <button type="button" class="cp-btn cp-btn-primario" id="btn_revisar">Marcar revisado</button>
        </footer>
    </dialog>

    <?php require_once("../MainJs/js.php");?>
    <script src="planificacion.js?v=<?php echo filemtime(__DIR__."/planificacion.js"); ?>"></script>
</body>
</html>
