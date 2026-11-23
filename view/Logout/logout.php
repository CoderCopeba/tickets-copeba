<?php
    require_once("../../config/conexion.php");

    /* Vaciar datos, borrar la cookie de sesión con los mismos parámetros y destruir la sesión */
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), "", array(
            "expires"  => time() - 42000,
            "path"     => $p["path"],
            "domain"   => $p["domain"],
            "secure"   => $p["secure"],
            "httponly" => $p["httponly"],
            "samesite" => $p["samesite"] ?? "Lax",
        ));
    }
    session_destroy();

    header("Location:".Conectar::ruta()."index.php");
    exit();
?>
