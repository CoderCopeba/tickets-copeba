<?php
    require_once("../../config/conexion.php");
    requiere_sesion("vista");
    /* Gerencia (no administradora ni de Informática): su Inicio son los Reportes */
    if (puede_ver_reportes() && !es_admin() && !es_soporte()){
        header("Location:".Conectar::ruta()."view/Reportes/");
        exit();
    }
    /* Qué Inicio ve: administrador, quien atiende tickets o colaborador */
    $vista = es_admin() ? "admin" : (es_soporte() ? "soporte" : "usuario");
    /* Animación de bienvenida solo justo después de iniciar sesión */
    $bienvenida = !empty($_SESSION["bienvenida"]);
    unset($_SESSION["bienvenida"]);
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Inicio</title>
</head>
<body class="with-side-menu"<?php if ($bienvenida) { ?> data-bienvenida="<?php echo e($_SESSION["usu_nom"] ?? ""); ?>"<?php } ?>>
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">

            <header class="cp-encabezado">
                <div>
                    <small>Sistema Interno · Cooperativa Copeba, R.L.</small>
                    <h1>Inicio</h1>
                </div>
                <a href="../NuevoTicket/" class="cp-btn cp-btn-primario">
                    <?php echo icono("mas", 14, 2.6); ?>
                    Nuevo ticket
                </a>
            </header>

            <?php if ($vista === "usuario") { ?>
            <section class="cp-tarjeta cp-bienvenida">
                <div>
                    <h2>Hola, <?php echo e($_SESSION["usu_nom"] ?? ""); ?></h2>
                    <p>¿Tienes un problema con tu equipo o necesitas un servicio? Repórtalo y te atendemos.</p>
                </div>
                <a href="../NuevoTicket/" class="cp-btn">Reportar un problema</a>
            </section>
            <?php } ?>

            <div class="cp-seccion-titulo">
                <h2><?php echo $vista === "admin" ? "Panel del sistema" : ($vista === "soporte" ? "Mi trabajo" : "Mis tickets"); ?></h2>
                <span class="cp-fecha" id="lbl_actualizado"></span>
            </div>

            <!-- Indicadores: el texto y los valores dependen del rol -->
            <section class="cp-kpis cp-cargando" id="kpis">
                <?php
                if ($vista === "admin") {
                    $kpis = array(
                        array("cuentas", "Cuentas activas", "Personal con acceso", "usuarios", ""),
                        array("temporales", "Temporales pendientes", "Aún no crean su contraseña", "llave", "ambar"),
                        array("sin_area", "Usuarios sin área", "Asígnales su área", "areas", "azul"),
                        array("fuera_tiempo", "Tickets fuera de tiempo", "Superaron su SLA", "cola", "gris"),
                    );
                } elseif ($vista === "soporte") {
                    $kpis = array(
                        array("mis_abiertos", "Asignados a mí", "Abiertos", "abierto", "azul"),
                        array("sin_asignar", "Sin asignar", "Esperan agente", "cola", "ambar"),
                        array("mis_cerrados", "Cerrados por mí", "Histórico", "cerrado", "gris"),
                        array("abiertos", "Abiertos en total", "Toda la mesa", "ticket", ""),
                    );
                } else {
                    $kpis = array(
                        array("total", "Mis tickets", "Histórico", "ticket", ""),
                        array("abiertos", "Abiertos", "En atención", "abierto", "azul"),
                        array("cerrados", "Cerrados", "Resueltos", "cerrado", "gris"),
                    );
                }
                foreach ($kpis as $k) { ?>
                <article class="cp-tarjeta cp-kpi">
                    <div class="cp-kpi-cab">
                        <span><?php echo e($k[1]); ?></span>
                        <span class="cp-kpi-ico <?php echo e($k[4]); ?>"><?php echo icono($k[3], 16, 2); ?></span>
                    </div>
                    <div class="cp-kpi-pie">
                        <span class="cp-kpi-num" data-kpi="<?php echo e($k[0]); ?>">–</span>
                        <span class="cp-kpi-nota" data-nota="<?php echo e($k[0]); ?>"><?php echo e($k[2]); ?></span>
                    </div>
                </article>
                <?php } ?>
            </section>

            <?php if ($vista === "admin") { ?>
            <!-- Panel del sistema: qué requiere atención (la actividad está en Reportes) -->
            <div class="cp-rejilla dos cp-panel-admin">
                <section class="cp-tarjeta cp-panel">
                    <div class="cp-seccion-titulo">
                        <div><h2>Tickets fuera de tiempo</h2><p>Abiertos que ya superaron su tiempo objetivo</p></div>
                        <a class="cp-enlace" href="../ConsultarTicket/">Ver tickets</a>
                    </div>
                    <ul class="cp-lista-atencion" id="lista_fuera_tiempo"></ul>
                </section>
                <section class="cp-tarjeta cp-panel">
                    <div class="cp-seccion-titulo">
                        <div><h2>Accesos rápidos</h2><p>Administración del sistema</p></div>
                    </div>
                    <div class="cp-accesos">
                        <a href="../MntUsuario/"><?php echo icono("usuarios", 20, 1.8); ?><span>Usuarios</span></a>
                        <a href="../ControlUsuarios/"><?php echo icono("llave", 20, 1.8); ?><span>Cuentas</span></a>
                        <a href="../MntArea/"><?php echo icono("areas", 20, 1.8); ?><span>Áreas y sedes</span></a>
                        <a href="../Reportes/"><?php echo icono("reportes", 20, 1.8); ?><span>Reportes</span></a>
                    </div>
                </section>
                <section class="cp-tarjeta cp-panel">
                    <div class="cp-seccion-titulo">
                        <div><h2>Usuarios sin área</h2><p>No verán su área hasta que se les asigne</p></div>
                        <a class="cp-enlace" href="../MntUsuario/">Ir a Usuarios</a>
                    </div>
                    <ul class="cp-lista-atencion" id="lista_sin_area"></ul>
                </section>
                <section class="cp-tarjeta cp-panel">
                    <div class="cp-seccion-titulo">
                        <div><h2>Cuentas y áreas</h2><p>Temporales vencidas y áreas activas sin integrantes</p></div>
                        <a class="cp-enlace" href="../ControlUsuarios/">Ir a Cuentas</a>
                    </div>
                    <ul class="cp-lista-atencion" id="lista_cuentas_areas"></ul>
                </section>
            </div>
            <?php } ?>

            <?php
            /* Tablas según el rol: [id, título, descripción] */
            if ($vista === "admin") {
                $tablas = array();   /* la lista completa está en Tickets */
            } elseif ($vista === "soporte") {
                $tablas = array(
                    array("asignados", "Asignados a mí", "Tickets abiertos que te corresponden, del más antiguo al más reciente"),
                    array("sin_asignar", "Sin asignar", "Solicitudes que todavía no tienen agente"),
                );
            } else {
                $tablas = array(array("recientes", "Mis solicitudes", "Estado de los tickets que has reportado"));
            }
            foreach ($tablas as $t) { ?>
            <section class="cp-rejilla">
                <div>
                    <div class="cp-seccion-titulo">
                        <div>
                            <h2><?php echo e($t[1]); ?></h2>
                            <p><?php echo e($t[2]); ?></p>
                        </div>
                        <a href="../ConsultarTicket/" class="cp-btn cp-btn-borde">Ver todos</a>
                    </div>
                    <div class="cp-tarjeta cp-tabla-caja">
                        <table class="cp-tabla">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Título</th>
                                    <th>Categoría</th>
                                    <th>Estado</th>
                                    <th>Soporte</th>
                                    <th>Fecha</th>
                                    <th data-orden="no">Detalle</th>
                                </tr>
                            </thead>
                            <tbody data-tabla="<?php echo e($t[0]); ?>">
                                <tr><td colspan="7" class="cp-vacio">Cargando…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php } ?>

        </div>
    </div>

    <?php require_once("../MainJs/js.php");?>
    <script src="home.js?v=<?php echo filemtime(__DIR__."/home.js"); ?>"></script>
</body>
</html>
