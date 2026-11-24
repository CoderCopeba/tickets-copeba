<?php
    /* Áreas, sedes y pertenencias. Gestión: solo Administrador del sistema. */
    require_once("../config/conexion.php");
    require_once("../models/Area.php");
    requiere_sesion("ajax");
    header("Content-Type: application/json; charset=utf-8");
    $area = new Area();

    function area_ok($extra = array()){
        echo json_encode(array_merge(array("status" => "ok"), $extra));
    }
    function area_error($mensaje){
        echo json_encode(array("status" => "error", "message" => $mensaje));
        exit();
    }
    function entero_o_nulo($valor){
        $n = (int)$valor;
        return $n > 0 ? $n : null;
    }

    switch ($_GET["op"] ?? "") {

        /* Cualquier persona: sus propias áreas (perfil, encabezados) */
        case "mis_areas":
            echo json_encode($area->areas_usuario(usuario_actual_id()));
            break;

        /* Listas para los formularios */
        case "catalogos":
            requiere_rol(array(ROL_ADMIN));
            echo json_encode(array(
                "areas"     => $area->areas(),
                "sedes"     => $area->sedes(),
                "funciones" => $area->funciones(),
            ));
            break;

        case "guardar_area":
            requiere_rol(array(ROL_ADMIN));
            $d = array(
                "area_id"       => entero_o_nulo($_POST["area_id"] ?? 0),
                "area_nom"      => trim(mb_substr((string)($_POST["area_nom"] ?? ""), 0, 80)),
                "area_descrip"  => trim(mb_substr((string)($_POST["area_descrip"] ?? ""), 0, 200)) ?: null,
                "area_padre_id" => entero_o_nulo($_POST["area_padre_id"] ?? 0),
                "area_cargo_id" => entero_o_nulo($_POST["area_cargo_id"] ?? 0),
                "area_estado"   => ($_POST["area_estado"] ?? "activa") === "planificada" ? "planificada" : "activa",
                "area_por_sede" => !empty($_POST["area_por_sede"]) ? 1 : 0,
                "area_atiende"  => !empty($_POST["area_atiende"]) ? 1 : 0,
            );
            if ($d["area_nom"] === ""){
                area_error("Escribe el nombre del área.");
            }
            foreach (array("area_padre_id", "area_cargo_id") as $campo){
                if ($d[$campo] !== null && (!$area->existe("tm_area", $d[$campo]) || $d[$campo] === $d["area_id"])){
                    area_error("El área seleccionada no es válida.");
                }
            }
            if ($d["area_estado"] === "activa"){
                $d["area_cargo_id"] = null;   /* solo las planificadas están "a cargo de" otra */
            }
            try {
                $id = $area->guardar_area($d);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000){
                    area_error("Ya existe un área con ese nombre.");
                }
                throw $e;
            }
            area_ok(array("area_id" => $id));
            break;

        case "desactivar_area":
            requiere_rol(array(ROL_ADMIN));
            $id = (int)($_POST["area_id"] ?? 0);
            $a = $area->area($id);
            if ($a === null){
                area_error("El área no existe.");
            }
            if ((int)$a["area_atiende"] === 1){
                area_error("No se puede eliminar un área que atiende tickets.");
            }
            if (count($area->miembros_area($id)) > 0){
                area_error("Quita primero a los integrantes del área.");
            }
            $area->desactivar_area($id);
            area_ok();
            break;

        case "miembros":
            requiere_rol(array(ROL_ADMIN));
            echo json_encode($area->miembros_area((int)($_POST["area_id"] ?? 0)));
            break;

        /* Pertenencias de una persona */
        case "membresias":
            requiere_rol(array(ROL_ADMIN));
            echo json_encode($area->areas_usuario((int)($_POST["usu_id"] ?? 0)));
            break;

        case "guardar_membresia":
            requiere_rol(array(ROL_ADMIN));
            $usu_id  = (int)($_POST["usu_id"] ?? 0);
            $area_id = (int)($_POST["area_id"] ?? 0);
            $sede_id = (int)($_POST["sede_id"] ?? 0);
            $fun_id  = (int)($_POST["fun_id"] ?? 3);
            $puesto  = trim(mb_substr((string)($_POST["au_puesto"] ?? ""), 0, 80)) ?: null;
            if (!$area->existe("tm_usuario", $usu_id) || !$area->existe("tm_area", $area_id)
                || !$area->existe("tm_sede", $sede_id) || !$area->existe("tm_funcion", $fun_id)){
                area_error("Revisa el área, la sede y la función.");
            }
            $area->guardar_membresia($usu_id, $area_id, $sede_id, $fun_id, $puesto);
            area_ok();
            break;

        case "quitar_membresia":
            requiere_rol(array(ROL_ADMIN));
            $area->quitar_membresia((int)($_POST["au_id"] ?? 0));
            area_ok();
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
