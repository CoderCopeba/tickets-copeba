<?php
    /* Crear la contraseña propia tras entrar con una temporal.
       Es lo ÚNICO que se permite mientras la contraseña sea temporal. */
    define("PERMITE_PASS_TEMPORAL", true);
    require_once("../config/conexion.php");
    require_once("../models/Usuario.php");
    requiere_sesion("ajax");
    header("Content-Type: application/json; charset=utf-8");

    function cuenta_error($mensaje){
        echo json_encode(array("status" => "error", "message" => $mensaje));
        exit();
    }

    switch ($_GET["op"] ?? "") {
        /* Administrador del sistema: ver el sistema como otra persona (solo lectura) */
        case "ver_como":
            if (viendo_como() || !es_admin()){
                auth_denegar(403, "ajax", "Solo un Administrador del sistema puede usar \"Ver como\".");
            }
            $destino = (int)($_POST["usu_id"] ?? 0);
            $usuario = new Usuario();
            $cuenta  = $usuario->cuenta_activa($destino);
            if ($cuenta === null){
                cuenta_error("La cuenta no existe o está desactivada.");
            }
            if ($destino === usuario_actual_id()){
                cuenta_error("Esa es tu propia cuenta.");
            }
            if ($cuenta["rol_id"] === ROL_ADMIN){
                cuenta_error("No se puede ver como otro Administrador del sistema.");
            }
            $admin = usuario_actual_id();
            $usuario->registrar_evento($destino, $admin, "vista_como", "Solo lectura");
            session_regenerate_id(true);
            $_SESSION["ver_como_admin"]  = $admin;
            $_SESSION["ver_como_nombre"] = trim(($_SESSION["usu_nom"] ?? "")." ".($_SESSION["usu_ape"] ?? ""));
            $_SESSION["usu_id"] = $destino;
            $_SESSION["rol_id"] = $cuenta["rol_id"];
            echo json_encode(array("status" => "ok"));
            break;

        /* Terminar "Ver como" y volver a la cuenta del administrador */
        case "volver":
            if (!viendo_como()){
                cuenta_error("No estás en modo \"Ver como\".");
            }
            $admin = (int)$_SESSION["ver_como_admin"];
            unset($_SESSION["ver_como_admin"], $_SESSION["ver_como_nombre"]);
            session_regenerate_id(true);
            $_SESSION["usu_id"] = $admin;
            $_SESSION["rol_id"] = ROL_ADMIN;   /* se vuelve a leer de la base en la siguiente petición */
            echo json_encode(array("status" => "ok"));
            break;

        case "crear_pass":
            if (!pass_temporal_pendiente()){
                cuenta_error("Tu contraseña ya está creada. Para cambiarla usa Perfil.");
            }
            $nueva   = (string)($_POST["pass_nueva"] ?? "");
            $repetir = (string)($_POST["pass_repetir"] ?? "");
            if (mb_strlen($nueva) < Usuario::PASS_MINIMO){
                cuenta_error("La contraseña debe tener al menos ".Usuario::PASS_MINIMO." caracteres.");
            }
            if (!hash_equals($nueva, $repetir)){
                cuenta_error("Las dos contraseñas no coinciden.");
            }
            $usuario = new Usuario();
            if ($usuario->es_pass_actual(usuario_actual_id(), $nueva)){
                cuenta_error("No puedes usar la contraseña temporal. Elige una nueva.");
            }
            $usuario->definir_pass(usuario_actual_id(), $nueva, "pass_creada");
            session_regenerate_id(true);
            echo json_encode(array("status" => "ok"));
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
