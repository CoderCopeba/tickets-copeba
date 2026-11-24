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
