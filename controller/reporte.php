<?php
    /* Reportes de actividad (solo lectura): administradores y Responsable de Gerencia */
    require_once("../config/conexion.php");
    require_once("../models/Reporte.php");
    requiere_sesion("ajax");
    if (!puede_ver_reportes()){
        auth_denegar(403, "ajax", "No tienes acceso a los reportes.");
    }
    header("Content-Type: application/json; charset=utf-8");

    function fecha_valida($f){
        $d = DateTime::createFromFormat("Y-m-d", (string)$f);
        return $d && $d->format("Y-m-d") === $f;
    }

    switch ($_GET["op"] ?? "") {
        case "resumen":
            $desde = (string)($_POST["desde"] ?? "");
            $hasta = (string)($_POST["hasta"] ?? "");
            if (!fecha_valida($desde) || !fecha_valida($hasta) || $desde > $hasta){
                echo json_encode(array("status" => "error", "message" => "Revisa las fechas del período."));
                break;
            }
            if ((strtotime($hasta) - strtotime($desde)) > 3 * 366 * 86400){
                echo json_encode(array("status" => "error", "message" => "El período máximo es de 3 años."));
                break;
            }
            $r = new Reporte($desde, $hasta);
            echo json_encode(array(
                "status"      => "ok",
                "indicadores" => $r->indicadores(),
                "tendencia"   => $r->tendencia(),
                "areas"       => $r->por_area(),
                "sedes"       => $r->por_sede(),
                "categorias"  => $r->por_categoria(),
                "urgencias"   => $r->por_urgencia(),
                "tecnicos"    => $r->por_tecnico(),
            ));
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
