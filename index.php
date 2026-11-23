<?php
    require_once("config/conexion.php");
    if(isset($_POST["enviar"]) and $_POST["enviar"]=="si"){
        require_once("models/Usuario.php");
        $usuario = new Usuario();
        $usuario->login();
    }

    /* Contacto del área de Informática (opcional, desde .env) */
    $soporte_correo    = env_get("SOPORTE_CORREO", false);
    $soporte_telefono  = env_get("SOPORTE_TELEFONO", false);
    $soporte_extension = env_get("SOPORTE_EXTENSION", false);

    $mensaje = $_GET["m"] ?? "";

    /* Versión automática para evitar la caché del navegador:
       añade ?v=<fecha de modificación>, que cambia cada vez que se edita el archivo */
    function recurso($ruta){
        $archivo = __DIR__."/".$ruta;
        return e($ruta).(is_file($archivo) ? "?v=".filemtime($archivo) : "");
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Copeba, R.L. · Iniciar sesión</title>
    <link rel="icon" type="image/png" href="<?php echo recurso("public/img/copeba/distintivo-copeba.png"); ?>">
    <link rel="stylesheet" href="<?php echo recurso("public/css/login-copeba.css"); ?>">
</head>
<body>

    <header class="cp-header">
        <!-- Respaldo: si el logo no carga, se muestra el nombre en texto -->
        <img src="<?php echo recurso("public/img/copeba/logo-copeba.png"); ?>" alt="Cooperativa Copeba, R.L."
             onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
        <span class="cp-logo-texto" hidden>Cooperativa <strong>Copeba, R.L.</strong></span>
        <span class="cp-portal">Portal Interno · Tickets</span>
    </header>

    <main class="cp-main">
        <form class="cp-card" action="" method="post" id="login_form" autocomplete="off" novalidate>

            <div class="cp-distintivo">
                <!-- Respaldo: si el distintivo no carga, se muestra la silueta de una persona -->
                <img src="<?php echo recurso("public/img/copeba/distintivo-copeba.png"); ?>" alt=""
                     onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                <svg class="cp-silueta" hidden width="56" height="56" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="8" r="4.2" fill="currentColor"/>
                    <path d="M3.5 21.5c0-4.6 3.8-7.5 8.5-7.5s8.5 2.9 8.5 7.5z" fill="currentColor"/>
                </svg>
            </div>

            <h1>Iniciar sesión</h1>
            <p class="cp-sub">Reporte y soporte técnico</p>

            <?php if ($mensaje === "1") { ?>
                <div class="cp-alerta" role="alert">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.5"/></svg>
                    El usuario y/o la contraseña son incorrectos.
                </div>
            <?php } elseif ($mensaje === "2") { ?>
                <div class="cp-alerta" role="alert">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.5"/></svg>
                    Escribe tu usuario y tu contraseña.
                </div>
            <?php } ?>

            <div class="cp-campo">
                <label for="usu_usuario">Usuario</label>
                <div class="cp-input">
                    <svg class="cp-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                    <input type="text" id="usu_usuario" name="usu_usuario" placeholder="Usuario" maxlength="30"
                           autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
                </div>
            </div>

            <div class="cp-campo">
                <label for="usu_pass">Contraseña</label>
                <div class="cp-input">
                    <svg class="cp-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input type="password" id="usu_pass" name="usu_pass" placeholder="Contraseña"
                           autocomplete="current-password" required>
                    <button type="button" class="cp-ojo" id="btn_ver_pass" aria-label="Mostrar contraseña" aria-pressed="false">
                        <svg id="ico_ojo" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <!-- Por seguridad no se guarda la sesión: termina al cerrar el navegador -->
            <div class="cp-fila-olvido">
                <button type="button" class="cp-link" id="btn_olvido">¿Olvidaste tu contraseña?</button>
            </div>

            <input type="hidden" name="enviar" value="si">
            <button type="submit" class="cp-boton">
                Acceder
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>
    </main>

    <footer class="cp-footer">
        <span>Cooperativa Copeba, R.L. · Uso exclusivo del personal autorizado</span>
        <span class="cp-mono">Soporte TI<?php if ($soporte_extension) { echo " · Ext. ".e($soporte_extension); } ?></span>
    </footer>

    <!-- ¿Olvidaste tu contraseña? -> contactar a Informática -->
    <dialog class="cp-modal" id="modal_olvido" aria-labelledby="modal_olvido_titulo">
        <div class="cp-modal-cab">
            <h2 id="modal_olvido_titulo">¿Olvidaste tu contraseña?</h2>
            <p>Por seguridad, la contraseña solo la restablece el área de Informática. Contáctanos por una de estas vías:</p>
        </div>
        <div class="cp-modal-cuerpo">
            <?php if ($soporte_correo) { ?>
            <a class="cp-opcion" id="lnk_correo" href="mailto:<?php echo e($soporte_correo); ?>">
                <span class="cp-opcion-ico">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 6 10 7 10-7"/></svg>
                </span>
                <div>
                    <strong>Enviar correo urgente</strong>
                    <span><?php echo e($soporte_correo); ?></span>
                </div>
            </a>
            <?php } ?>

            <?php if ($soporte_telefono || $soporte_extension) { ?>
            <a class="cp-opcion" href="<?php echo $soporte_telefono ? 'tel:'.e(preg_replace('/[^0-9+]/', '', $soporte_telefono)) : '#'; ?>">
                <span class="cp-opcion-ico">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>
                </span>
                <div>
                    <strong>Llamar a Informática</strong>
                    <span>
                        <?php echo e($soporte_telefono ?? ""); ?>
                        <?php if ($soporte_extension) { echo ($soporte_telefono ? " · " : "")."Ext. ".e($soporte_extension); } ?>
                    </span>
                </div>
            </a>
            <?php } ?>

            <?php if (!$soporte_correo && !$soporte_telefono && !$soporte_extension) { ?>
            <p class="cp-modal-nota">Comunícate directamente con el área de Informática.</p>
            <?php } ?>
            <p class="cp-modal-nota">Nunca compartas tu contraseña por correo ni por teléfono; Informática te asignará una temporal.</p>
        </div>
        <div class="cp-modal-pie">
            <button type="button" class="cp-boton-sec" id="btn_cerrar_olvido">Cerrar</button>
        </div>
    </dialog>

<script>
(function () {
    /* Mostrar / ocultar contraseña */
    var pass = document.getElementById('usu_pass');
    var ojo = document.getElementById('btn_ver_pass');
    var ico = document.getElementById('ico_ojo');
    var ojoAbierto = ico.innerHTML;
    var ojoCerrado = '<path d="M17.9 17.9A10.1 10.1 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 5.1-5.9M9.9 4.2A9.1 9.1 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2"/>';
    ojo.addEventListener('click', function () {
        var ver = pass.type === 'password';
        pass.type = ver ? 'text' : 'password';
        ico.innerHTML = ver ? ojoCerrado : ojoAbierto;
        ojo.setAttribute('aria-pressed', ver ? 'true' : 'false');
        ojo.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
        pass.focus();
    });

    /* Correo urgente: asunto y cuerpo prellenados con el usuario escrito */
    var modal = document.getElementById('modal_olvido');
    var lnkCorreo = document.getElementById('lnk_correo');
    var baseCorreo = lnkCorreo ? lnkCorreo.getAttribute('href') : null;

    document.getElementById('btn_olvido').addEventListener('click', function () {
        if (lnkCorreo) {
            var usuario = document.getElementById('usu_usuario').value.trim() || '(escriba aquí su usuario)';
            var asunto = 'URGENTE - Restablecer contraseña del sistema de tickets';
            var cuerpo = 'Buen día, área de Informática:\n\n'
                + 'Solicito restablecer mi contraseña del sistema de tickets.\n\n'
                + 'Usuario: ' + usuario + '\n'
                + 'Nombre completo: \n'
                + 'Agencia / área: \n'
                + 'Teléfono o extensión de contacto: \n\n'
                + 'Gracias.';
            lnkCorreo.setAttribute('href', baseCorreo + '?subject=' + encodeURIComponent(asunto) + '&body=' + encodeURIComponent(cuerpo));
        }
        if (typeof modal.showModal === 'function') { modal.showModal(); } else { modal.setAttribute('open', ''); }
    });
    document.getElementById('btn_cerrar_olvido').addEventListener('click', function () { modal.close ? modal.close() : modal.removeAttribute('open'); });
    modal.addEventListener('click', function (e) { if (e.target === modal) { modal.close(); } });
})();
</script>
</body>
</html>
