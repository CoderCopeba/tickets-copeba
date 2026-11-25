<?php
    /* Primer ingreso con contraseña temporal: la persona crea la suya */
    define("PERMITE_PASS_TEMPORAL", true);
    require_once("../../config/conexion.php");
    requiere_sesion("vista");
    if (!pass_temporal_pendiente()){
        header("Location:".Conectar::ruta()."view/Home/");
        exit();
    }
    require_once("../../models/Usuario.php");
    function recurso($ruta){
        $archivo = __DIR__."/../../".$ruta;
        return "../../".e($ruta).(is_file($archivo) ? "?v=".filemtime($archivo) : "");
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Copeba · Crea tu contraseña</title>
    <link rel="icon" type="image/png" href="<?php echo recurso("public/img/copeba/distintivo-copeba.png"); ?>">
    <link rel="stylesheet" href="<?php echo recurso("public/css/login-copeba.css"); ?>">
</head>
<body>
    <header class="cp-header">
        <img src="<?php echo recurso("public/img/copeba/logo-copeba.png"); ?>" alt="Cooperativa Copeba, R.L.">
        <span class="cp-portal">Portal Interno · Tickets</span>
    </header>

    <main class="cp-main">
        <form class="cp-card" id="frm_pass" novalidate>
            <div class="cp-distintivo">
                <img src="<?php echo recurso("public/img/copeba/distintivo-copeba.png"); ?>" alt="">
            </div>

            <h1>Crea tu contraseña</h1>
            <p class="cp-sub">Hola, <?php echo e($_SESSION["usu_nom"] ?? ""); ?>. Entraste con una contraseña temporal de Informática. Elige ahora la tuya; solo tú la conocerás.</p>

            <div class="cp-alerta" role="alert" id="alerta" hidden></div>

            <div class="cp-campo">
                <label for="pass_nueva">Nueva contraseña</label>
                <div class="cp-input">
                    <svg class="cp-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input type="password" id="pass_nueva" name="pass_nueva" minlength="<?php echo Usuario::PASS_MINIMO; ?>" autocomplete="new-password" required autofocus>
                    <button type="button" class="cp-ojo" data-ver="pass_nueva" aria-label="Mostrar contraseña" aria-pressed="false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
            </div>
            <div class="cp-campo">
                <label for="pass_repetir">Escríbela otra vez</label>
                <div class="cp-input">
                    <svg class="cp-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input type="password" id="pass_repetir" name="pass_repetir" autocomplete="new-password" required>
                    <button type="button" class="cp-ojo" data-ver="pass_repetir" aria-label="Mostrar contraseña" aria-pressed="false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
            </div>

            <ul class="cp-reglas" id="reglas">
                <li data-regla="largo">Al menos <?php echo Usuario::PASS_MINIMO; ?> caracteres</li>
                <li data-regla="igual">Las dos coinciden</li>
            </ul>

            <button type="submit" class="cp-boton" id="btn_guardar">Guardar y entrar</button>
            <p class="cp-modal-nota" style="text-align:center;margin-top:14px">
                <a class="cp-link" href="../Logout/logout.php">Cancelar y cerrar sesión</a>
            </p>
        </form>
    </main>

    <footer class="cp-footer">
        <span>Cooperativa Copeba, R.L. · Uso exclusivo del personal autorizado</span>
        <span class="cp-mono">Soporte TI</span>
    </footer>

<script>
(function () {
    var MIN = <?php echo (int)Usuario::PASS_MINIMO; ?>;
    var nueva = document.getElementById('pass_nueva');
    var repetir = document.getElementById('pass_repetir');
    var alerta = document.getElementById('alerta');
    var boton = document.getElementById('btn_guardar');

    function revisar() {
        var largo = nueva.value.length >= MIN;
        var igual = nueva.value !== '' && nueva.value === repetir.value;
        document.querySelector('[data-regla="largo"]').classList.toggle('ok', largo);
        document.querySelector('[data-regla="igual"]').classList.toggle('ok', igual);
        return largo && igual;
    }
    function mostrar(texto) { alerta.textContent = texto; alerta.hidden = false; }

    /* Ojo: mostrar u ocultar lo que se escribe */
    document.querySelectorAll('[data-ver]').forEach(function (b) {
        b.addEventListener('click', function () {
            var i = document.getElementById(b.getAttribute('data-ver'));
            var ver = i.type === 'password';
            i.type = ver ? 'text' : 'password';
            b.setAttribute('aria-pressed', ver ? 'true' : 'false');
            b.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
            b.style.color = ver ? 'var(--verde-oscuro)' : '';
            i.focus();
        });
    });
    nueva.addEventListener('input', revisar);
    repetir.addEventListener('input', revisar);

    document.getElementById('frm_pass').addEventListener('submit', function (e) {
        e.preventDefault();
        alerta.hidden = true;
        if (!revisar()) {
            mostrar(nueva.value.length < MIN ? 'La contraseña debe tener al menos ' + MIN + ' caracteres.' : 'Las dos contraseñas no coinciden.');
            return;
        }
        boton.disabled = true;
        var datos = new FormData();
        datos.append('pass_nueva', nueva.value);
        datos.append('pass_repetir', repetir.value);
        fetch('../../controller/cuenta.php?op=crear_pass', {
            method: 'POST',
            body: datos,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }
        }).then(function (r) { return r.json(); }).then(function (r) {
            if (r && r.status === 'ok') { window.location.href = '../Home/'; return; }
            mostrar((r && r.message) || 'No se pudo guardar la contraseña.');
            boton.disabled = false;
        }).catch(function () {
            mostrar('No se pudo conectar. Inténtalo de nuevo.');
            boton.disabled = false;
        });
    });
})();
</script>
</body>
</html>
