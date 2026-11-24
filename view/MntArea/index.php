<?php
    require_once("../../config/conexion.php");
    requiere_rol(array(ROL_ADMIN), "vista");   /* solo Administrador del sistema */
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Áreas y sedes</title>
</head>
<body class="with-side-menu">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">

            <header class="cp-encabezado">
                <div>
                    <small>Administración del sistema · Copeba, R.L.</small>
                    <h1>Áreas y sedes</h1>
                </div>
                <button type="button" class="cp-btn cp-btn-primario" id="btn_nueva"><?php echo icono("mas", 14, 2.6); ?> Nueva área</button>
            </header>

            <div class="cp-kpis" id="resumen"></div>

            <div class="cp-rejilla">
                <section>
                    <div class="cp-seccion-titulo">
                        <div>
                            <h2>Áreas</h2>
                            <p>Las áreas planificadas existen, pero las cubre otra área hasta tener personal propio.</p>
                        </div>
                    </div>
                    <div class="cp-tarjeta cp-tabla-caja">
                        <table class="cp-tabla">
                            <thead>
                                <tr><th>Área</th><th>Estado</th><th>Alcance</th><th>Integrantes</th><th class="cp-der">Acciones</th></tr>
                            </thead>
                            <tbody id="areas"><tr><td colspan="5" class="cp-vacio">Cargando…</td></tr></tbody>
                        </table>
                    </div>
                </section>

                <aside>
                    <div class="cp-seccion-titulo"><div><h2>Sedes</h2><p>Sede central (administración y agencia), agencias y supermercado.</p></div></div>
                    <div class="cp-tarjeta cp-panel">
                        <ul class="cp-lista-sedes cp-lista-sedes-rejilla" id="sedes"></ul>
                    </div>
                </aside>
            </div>

        </div>
    </div>

    <!-- Alta / edición de área -->
    <dialog class="cp-dialogo" id="dlg_area" aria-labelledby="dlg_area_titulo">
        <form id="area_form" method="dialog" novalidate>
            <header><h2 id="dlg_area_titulo">Nueva área</h2></header>
            <div class="cp-dialogo-cuerpo">
                <input type="hidden" id="area_id" name="area_id">
                <div class="cp-campo"><label for="area_nom">Nombre</label><input class="cp-control" id="area_nom" name="area_nom" maxlength="80" required></div>
                <div class="cp-campo"><label for="area_descrip">Descripción</label><input class="cp-control" id="area_descrip" name="area_descrip" maxlength="200" placeholder="Qué hace el área"></div>
                <div class="cp-campos-2">
                    <div class="cp-campo">
                        <label for="area_padre_id">Pertenece a</label>
                        <select class="cp-control" id="area_padre_id" name="area_padre_id"></select>
                    </div>
                    <div class="cp-campo">
                        <label for="area_estado">Estado</label>
                        <select class="cp-control" id="area_estado" name="area_estado">
                            <option value="activa">Activa (tiene personal)</option>
                            <option value="planificada">Planificada</option>
                        </select>
                    </div>
                </div>
                <div class="cp-campo" id="campo_cargo">
                    <label for="area_cargo_id">Mientras tanto la cubre</label>
                    <select class="cp-control" id="area_cargo_id" name="area_cargo_id"></select>
                </div>
                <label class="cp-check"><input type="checkbox" id="area_por_sede" name="area_por_sede" value="1"> Existe en cada agencia (Caja, Créditos…)</label>
                <label class="cp-check"><input type="checkbox" id="area_atiende" name="area_atiende" value="1"> Atiende tickets (como Informática)</label>
            </div>
            <footer>
                <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cancelar</button>
                <button type="submit" class="cp-btn cp-btn-primario">Guardar</button>
            </footer>
        </form>
    </dialog>

    <!-- Integrantes de un área -->
    <dialog class="cp-dialogo cp-dialogo-ancho" id="dlg_miembros" aria-labelledby="dlg_miembros_titulo">
        <header><h2 id="dlg_miembros_titulo">Integrantes</h2><p>Para agregar o quitar personas, usa la pantalla Usuarios → Áreas.</p></header>
        <div class="cp-dialogo-cuerpo"><ul class="cp-membresias" id="miembros"></ul></div>
        <footer><a class="cp-btn cp-btn-borde" href="../MntUsuario/">Ir a Usuarios</a><button type="button" class="cp-btn cp-btn-primario" data-cerrar>Listo</button></footer>
    </dialog>

    <?php require_once("../MainJs/js.php");?>
    <script src="mntarea.js?v=<?php echo filemtime(__DIR__."/mntarea.js"); ?>"></script>
</body>
</html>
