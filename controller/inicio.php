<?php
    /* Datos de la pantalla de Inicio: cada rol recibe solo lo que le corresponde */
    require_once("../config/conexion.php");
    requiere_sesion("ajax");
    require_once("../models/Inicio.php");

    header("Content-Type: application/json; charset=utf-8");
    $inicio = new Inicio();

    switch ($_GET["op"] ?? "") {
        case "resumen":
            $rol = rol_actual();
            if ($rol === ROL_ADMIN) {
                $datos = $inicio->resumen_admin();
            } elseif ($rol === ROL_SOPORTE) {
                $datos = $inicio->resumen_soporte(usuario_actual_id());
            } else {
                $datos = $inicio->resumen_usuario(usuario_actual_id());
            }
            $datos["rol"] = $rol;
            echo json_encode($datos);
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
