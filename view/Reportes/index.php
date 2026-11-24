<?php
    require_once("../../config/conexion.php");
    requiere_sesion("vista");
    if (!puede_ver_reportes()){
        auth_denegar(403, "vista", "Los reportes son para Gerencia y para los administradores del sistema.");
    }
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Reportes</title>
</head>
<body class="with-side-menu cp-reportes">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">

            <header class="cp-encabezado">
                <div>
                    <small>Gerencia · Mesa de Ayuda · Copeba, R.L.</small>
                    <h1>Reportes de actividad</h1>
                </div>
                <div class="cp-encabezado-acciones cp-no-imprimir">
                    <button type="button" class="cp-btn cp-btn-borde" id="btn_exportar">Exportar</button>
                    <button type="button" class="cp-btn cp-btn-primario" id="btn_imprimir">Imprimir / PDF</button>
                </div>
            </header>

            <!-- Período: una sola fila de filtros encima de todo -->
            <div class="cp-periodo cp-no-imprimir">
                <div class="cp-listado-pestanas" role="tablist" id="periodos">
                    <button type="button" data-p="mes" class="activa">Este mes</button>
                    <button type="button" data-p="anterior">Mes anterior</button>
                    <button type="button" data-p="3m">Últimos 3 meses</button>
                    <button type="button" data-p="6m">Últimos 6 meses</button>
                    <button type="button" data-p="anio">Este año</button>
                </div>
                <div class="cp-periodo-rango">
                    <label>Desde <input type="date" class="cp-control" id="desde"></label>
                    <label>Hasta <input type="date" class="cp-control" id="hasta"></label>
                </div>
            </div>
            <p class="cp-periodo-texto" id="periodo_texto"></p>

            <section class="cp-kpis" id="indicadores" aria-label="Indicadores del período"></section>

            <section class="cp-tarjeta cp-panel cp-grafica">
                <div class="cp-seccion-titulo">
                    <div>
                        <h2>Tendencia mensual</h2>
                        <p>Tickets recibidos y resueltos cada mes</p>
                    </div>
                    <div class="cp-leyenda" aria-hidden="true">
                        <span><i class="s1"></i>Recibidos</span>
                        <span><i class="s2"></i>Resueltos</span>
                    </div>
                </div>
                <div class="cp-columnas" id="tendencia" role="img" aria-label="Gráfica de tickets recibidos y resueltos por mes"></div>
                <details class="cp-datos">
                    <summary>Ver datos en tabla</summary>
                    <table class="cp-tabla" id="tendencia_tabla"></table>
                </details>
            </section>

            <div class="cp-rejilla dos">
                <section class="cp-tarjeta cp-panel">
                    <div class="cp-seccion-titulo"><div><h2>Áreas con más actividad</h2><p>Tickets recibidos según el área de quien los pidió</p></div></div>
                    <div class="cp-barras-h" id="areas"></div>
                </section>
                <section class="cp-tarjeta cp-panel">
                    <div class="cp-seccion-titulo"><div><h2>Actividad por sede</h2><p>Sede central, agencias y supermercado</p></div></div>
                    <div class="cp-barras-h" id="sedes"></div>
                </section>
                <section class="cp-tarjeta cp-panel">
                    <div class="cp-seccion-titulo"><div><h2>Por categoría</h2><p>Qué tipo de problema se reporta</p></div></div>
                    <div class="cp-barras-h" id="categorias"></div>
                </section>
                <section class="cp-tarjeta cp-panel">
                    <div class="cp-seccion-titulo"><div><h2>Por urgencia</h2><p>Cómo calificaron la urgencia quienes reportaron</p></div></div>
                    <div class="cp-barras-h" id="urgencias"></div>
                </section>
            </div>

            <section class="cp-tarjeta cp-tabla-caja cp-tecnicos">
                <div class="cp-seccion-titulo cp-tabla-titulo"><div><h2>Equipo de Informática</h2><p>Carga y resultados de cada técnico en el período</p></div></div>
                <table class="cp-tabla">
                    <thead><tr><th>Técnico</th><th class="cp-der">Asignados</th><th>Resueltos</th><th class="cp-der">Pendientes hoy</th><th class="cp-der">Tiempo promedio</th></tr></thead>
                    <tbody id="tecnicos"></tbody>
                </table>
            </section>

            <div class="cp-tooltip" id="tooltip" role="tooltip" hidden></div>
        </div>
    </div>

    <?php require_once("../MainJs/js.php");?>
    <script src="reportes.js?v=<?php echo filemtime(__DIR__."/reportes.js"); ?>"></script>
</body>
</html>
