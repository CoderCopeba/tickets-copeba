<?php
    /* Datos de la pantalla de Inicio: cada rol recibe solo lo que le corresponde */
    require_once("../config/conexion.php");
    requiere_sesion("ajax");
    require_once("../models/Inicio.php");

    header("Content-Type: application/json; charset=utf-8");
    $inicio = new Inicio();

    switch ($_GET["op"] ?? "") {
        case "resumen":
            if (es_admin()) {
                $datos = $inicio->resumen_admin();
            } elseif (es_soporte()) {
                $datos = $inicio->resumen_soporte(usuario_actual_id());
            } else {
                $datos = $inicio->resumen_usuario(usuario_actual_id());
            }
            $datos["vista"] = es_admin() ? "admin" : (es_soporte() ? "soporte" : "usuario");
            echo json_encode($datos);
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
