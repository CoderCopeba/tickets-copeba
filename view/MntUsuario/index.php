<?php
    require_once("../../config/conexion.php");
    requiere_rol(array(ROL_ADMIN), "vista");   /* solo Administrador del sistema */
?>
<!DOCTYPE html>
<html lang="es">
    <?php require_once("../MainHead/head.php");?>
    <title>Copeba · Usuarios</title>
</head>
<body class="with-side-menu">
    <?php require_once("../MainHeader/header.php");?>
    <?php require_once("../MainNav/nav.php");?>

    <div class="page-content">
        <div class="container-fluid">

            <header class="cp-encabezado">
                <div>
                    <small>Administración del sistema · Copeba, R.L.</small>
                    <h1>Usuarios</h1>
                </div>
                <p class="cp-encabezado-nota">El área define qué ve y hace cada persona. "Administrador del sistema" es solo para Informática.</p>
            </header>

            <div id="lista_usuarios"></div>

        </div>
    </div>

    <!-- Alta / edición de usuario -->
    <dialog class="cp-dialogo" id="dlg_usuario" aria-labelledby="dlg_usuario_titulo">
        <form id="usuario_form" method="dialog" novalidate>
            <header><h2 id="dlg_usuario_titulo">Nuevo usuario</h2></header>
            <div class="cp-dialogo-cuerpo">
                <input type="hidden" id="usu_id" name="usu_id">
                <div class="cp-campos-2">
                    <div class="cp-campo"><label for="usu_nom">Nombre</label><input class="cp-control" id="usu_nom" name="usu_nom" maxlength="150" required></div>
                    <div class="cp-campo"><label for="usu_ape">Apellido</label><input class="cp-control" id="usu_ape" name="usu_ape" maxlength="150" required></div>
                </div>
                <div class="cp-campos-2">
                    <div class="cp-campo">
                        <label for="usu_usuario">Usuario</label>
                        <input class="cp-control" id="usu_usuario" name="usu_usuario" maxlength="30" placeholder="ej. jperez" autocomplete="off" autocapitalize="none" spellcheck="false" required>
                    </div>
                    <div class="cp-campo"><label for="usu_correo">Correo</label><input type="email" class="cp-control" id="usu_correo" name="usu_correo" maxlength="150" required></div>
                </div>
                <div class="cp-campos-2">
                    <div class="cp-campo">
                        <label for="sede_id">Agencia / sede</label>
                        <select class="cp-control" id="sede_id" name="sede_id"></select>
                    </div>
                    <div class="cp-campo">
                        <label for="rol_id">Tipo de cuenta</label>
                        <select class="cp-control" id="rol_id" name="rol_id">
                            <option value="<?php echo ROL_COLABORADOR; ?>">Colaborador</option>
                            <option value="<?php echo ROL_ADMIN; ?>">Administrador del sistema (solo Informática)</option>
                        </select>
                    </div>
                </div>

                <!-- Alta: área de trabajo en el mismo paso -->
                <fieldset class="cp-grupo" id="grupo_area_alta">
                    <legend>Área de trabajo</legend>
                    <div class="cp-campos-2">
                        <div class="cp-campo">
                            <label for="area_id">Área</label>
                            <select class="cp-control" id="area_id" name="area_id"></select>
                        </div>
                        <div class="cp-campo">
                            <label for="fun_id">Función</label>
                            <select class="cp-control" id="fun_id" name="fun_id"></select>
                        </div>
                    </div>
                    <div class="cp-campo">
                        <label for="au_puesto">Puesto</label>
                        <input class="cp-control" id="au_puesto" name="au_puesto" maxlength="80" placeholder="Ej. Cajera 2, Asesor de créditos, Contadora general">
                        <small class="cp-ayuda">Podrás agregarle más áreas después con el botón Áreas.</small>
                    </div>
                </fieldset>

                <!-- Edición: áreas actuales -->
                <div class="cp-grupo" id="grupo_area_edicion" hidden>
                    <div class="cp-grupo-cab">
                        <strong>Áreas de trabajo</strong>
                        <button type="button" class="cp-btn cp-btn-borde cp-btn-chico" id="btn_areas_desde_editar">Administrar áreas</button>
                    </div>
                    <div class="cp-chips" id="areas_actuales"></div>
                </div>
                <div class="cp-campo">
                    <label for="usu_pass">Contraseña</label>
                    <input type="password" class="cp-control" id="usu_pass" name="usu_pass" autocomplete="new-password">
                    <small class="cp-ayuda" id="usu_pass_ayuda">Obligatoria para un usuario nuevo.</small>
                </div>
            </div>
            <footer>
                <button type="button" class="cp-btn cp-btn-borde" data-cerrar>Cancelar</button>
                <button type="submit" class="cp-btn cp-btn-primario">Guardar</button>
            </footer>
        </form>
    </dialog>

    <!-- Áreas de una persona -->
    <dialog class="cp-dialogo cp-dialogo-ancho" id="dlg_areas" aria-labelledby="dlg_areas_titulo">
        <header><h2 id="dlg_areas_titulo">Áreas</h2><p>Una persona puede pertenecer a varias áreas y sedes.</p></header>
        <div class="cp-dialogo-cuerpo">
            <ul class="cp-membresias" id="membresias"></ul>
            <form class="cp-membresia-nueva" id="membresia_form" novalidate>
                <select class="cp-control" id="m_area" aria-label="Área" required></select>
                <select class="cp-control" id="m_sede" aria-label="Sede" required></select>
                <select class="cp-control" id="m_funcion" aria-label="Función"></select>
                <input class="cp-control" id="m_puesto" maxlength="80" placeholder="Puesto (ej. Cajera 2)" aria-label="Puesto">
                <button type="submit" class="cp-btn cp-btn-primario">Agregar</button>
            </form>
        </div>
        <footer><button type="button" class="cp-btn cp-btn-borde" data-cerrar>Listo</button></footer>
    </dialog>

    <?php require_once("../MainJs/js.php");?>
    <script src="mntusuario.js?v=<?php echo filemtime(__DIR__."/mntusuario.js"); ?>"></script>
</body>
</html>
