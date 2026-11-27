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
                    <small>Sistema Interno · Cooperativa Copeba, R.L.</small>
                    <h1>Planificación</h1>
                </div>
                <p class="cp-encabezado-nota">Lo que planeas hacer en la semana o el mes, y cómo te fue</p>
            </header>

            <!-- Filtro: tipo de plan y rango Desde–Hasta (con atajos). Se ven todos los planes del rango. -->
            <form class="cp-tarjeta cp-plan-filtro" id="filtro_form" novalidate>
                <div class="cp-plan-tipo" role="tablist" aria-label="Tipo de plan">
                    <button type="button" role="tab" data-tipo="semana" class="activa" aria-selected="true">Semanal</button>
                    <button type="button" role="tab" data-tipo="mes" aria-selected="false">Mensual</button>
                </div>
                <div class="cp-plan-rango">
                    <label for="f_desde">Desde</label>
                    <input type="date" id="f_desde" data-rango-con="#f_hasta">
                    <label for="f_hasta">Hasta</label>
                    <input type="date" id="f_hasta" data-rango-con="#f_desde">
                    <button type="submit" class="cp-btn cp-btn-primario cp-btn-chico" id="btn_filtrar">Ver</button>
                </div>
                <div class="cp-plan-atajos" id="atajos" aria-label="Atajos de fechas"></div>
            </form>

            <!-- Un bloque por semana (o mes) del rango; se llena desde planificacion.js -->
            <div id="planes" aria-live="polite"></div>

            <!-- Solo si la persona dirige algún área (el servidor lo decide) -->
            <div id="lista_equipo" hidden></div>

            <?php if (es_soporte()) { ?>
            <!-- Solo TI: notas internas de los tickets (ej. "se acabó la pasta térmica")
                 para tomarlas en cuenta al planificar. El servidor vuelve a comprobar el permiso. -->
            <div id="lista_notas"></div>
            <?php } ?>
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

    <!-- Planificar a partir de una nota interna de TI (ej. "se acabaron los teclados" ->
         "Nota interna 004 · Salida por suministros"). Solo TI; el servidor lo comprueba. -->
    <dialog class="cp-dialogo cp-dialogo-ancho" id="dlg_nota" aria-labelledby="nt_titulo">
        <form id="nota_form" method="dialog" novalidate>
            <header><h2 id="nt_titulo">Planificar desde una nota</h2><p>Notas internas de TI en tickets abiertos (últimos 60 días). La actividad queda con la referencia a la nota.</p></header>
            <div class="cp-dialogo-cuerpo">
                <input type="search" class="cp-control" id="nt_buscar" placeholder="Buscar en las notas, el ticket o quién la escribió…" aria-label="Buscar nota" autocomplete="off">
                <div class="cp-plan-tickets" id="nt_lista" role="radiogroup" aria-label="Notas internas"></div>
                <div class="cp-campos-2">
                    <div class="cp-campo">
                        <label for="nt_texto">Actividad</label>
                        <input type="text" class="cp-control" id="nt_texto" maxlength="250" autocomplete="off" placeholder="P. ej. Salida por suministros">
                    </div>
                    <div class="cp-campo">
                        <label for="nt_dia">Día (opcional)</label>
                        <input type="date" id="nt_dia">
                    </div>
                </div>
            </div>
            <footer>
                <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cancelar</button>
                <button type="submit" class="cp-btn cp-btn-primario" id="nt_enviar">Agregar al plan</button>
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
