<?php
/* ===== Protección CSRF =====
   Cada sesión tiene un token aleatorio. Las peticiones POST deben enviarlo
   (cabecera X-CSRF-Token en AJAX o campo csrf_token en formularios). */
function csrf_token(){
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_valido(){
    $enviado = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? ($_POST["csrf_token"] ?? "");
    return is_string($enviado) && $enviado !== ""
        && !empty($_SESSION["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $enviado);
}

function es_post(){
    return ($_SERVER["REQUEST_METHOD"] ?? "") === "POST";
}

/* ===== Límite de intentos de inicio de sesión =====
   5 fallos por usuario+IP o 20 por IP en 15 minutos -> bloqueo de 15 minutos.
   Se guarda en archivos temporales del servidor (no requiere tablas nuevas). */
const LOGIN_MAX_USUARIO = 5;
const LOGIN_MAX_IP      = 20;
const LOGIN_VENTANA     = 900;   /* segundos */

function login_claves($usuario){
    $ip = $_SERVER["REMOTE_ADDR"] ?? "cli";
    return array(
        "u" => hash("sha256", "u|".$ip."|".$usuario),
        "i" => hash("sha256", "i|".$ip),
    );
}

function login_archivo($clave){
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR."copeba_login";
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    return $dir.DIRECTORY_SEPARATOR.$clave.".json";
}

function login_leer($clave){
    $f = login_archivo($clave);
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    if (!is_array($d) || (time() - ($d["t"] ?? 0)) > LOGIN_VENTANA) {
        return array("n" => 0, "t" => time());
    }
    return $d;
}

function login_bloqueado($usuario){
    $k = login_claves($usuario);
    return login_leer($k["u"])["n"] >= LOGIN_MAX_USUARIO
        || login_leer($k["i"])["n"] >= LOGIN_MAX_IP;
}

function login_fallo($usuario){
    foreach (login_claves($usuario) as $clave) {
        $d = login_leer($clave);
        $d["n"]++;
        @file_put_contents(login_archivo($clave), json_encode($d), LOCK_EX);
    }
}

function login_exito($usuario){
    @unlink(login_archivo(login_claves($usuario)["u"]));
}
