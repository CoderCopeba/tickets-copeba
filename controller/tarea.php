<?php
    /* Tareas por área. Asignan: Responsable/Suplente del grupo o el Administrador.
       Cada persona asignada solo ve y mueve su propio avance. */
    require_once("../config/conexion.php");
    require_once("../models/Tarea.php");
    require_once("../models/Prioridad.php");
    requiere_sesion("ajax");
    header("Content-Type: application/json; charset=utf-8");
    $tarea = new Tarea();
    $yo = usuario_actual_id();

    function tarea_ok($extra = array()){
        echo json_encode(array_merge(array("status" => "ok"), $extra));
        exit();
    }
    function tarea_error($mensaje){
        echo json_encode(array("status" => "error", "message" => $mensaje));
        exit();
    }
    function texto_post($campo, $max){
        return trim(mb_substr((string)($_POST[$campo] ?? ""), 0, $max));
    }
    /* Tarea vigente que la persona puede dirigir; si no, 403/404 */
    function tarea_gestionable(Tarea $tarea, $tarea_id){
        $t = $tarea->tarea($tarea_id);
        if ($t === null){ auth_denegar(404, "ajax", "La tarea no existe o fue anulada."); }
        $grupos = $tarea->grupos_asignables(usuario_actual_id(), es_admin());
        if (!$tarea->puede_gestionar($t, usuario_actual_id(), es_admin(), $grupos)){
            auth_denegar(403, "ajax", "Solo quien dirige el área puede hacer esto.");
        }
        return $t;
    }

    switch ($_GET["op"] ?? "") {

        /* Mis tareas + (si dirijo algún grupo) las que asigné y los datos del formulario */
        case "lista":
            $grupos = $tarea->grupos_asignables($yo, es_admin());
            $datos = array("mias" => $tarea->mis_tareas($yo), "dirige" => count($grupos) > 0);
            if ($datos["dirige"]){
                $datos["asignadas"]   = $tarea->asignadas($yo, es_admin());
                $datos["grupos"]      = $grupos;
                $datos["prioridades"] = (new Prioridad())->listar();
            }
            echo json_encode($datos);
            break;

        /* Personas de un grupo que puedo asignar */
        case "personas":
            $grupos = $tarea->grupos_asignables($yo, es_admin());
            $g = Tarea::grupo_permitido($grupos, $_POST["area_id"] ?? 0, $_POST["sede_id"] ?? 0);
            if ($g === null){ auth_denegar(403, "ajax", "No diriges esa área."); }
            echo json_encode($tarea->personas_grupo($g["area_id"], $g["sede_id"], $yo));
            break;

        case "crear":
            $grupos = $tarea->grupos_asignables($yo, es_admin());
            $g = Tarea::grupo_permitido($grupos, $_POST["area_id"] ?? 0, $_POST["sede_id"] ?? 0);
            if ($g === null){ auth_denegar(403, "ajax", "Solo el Responsable o Suplente del área puede asignarle tareas."); }

            $titulo  = texto_post("titulo", 200);
            $descrip = html_limpio($_POST["descrip"] ?? "");
            $prio_id = (int)($_POST["prio_id"] ?? Prioridad::NORMAL);
            $limite  = (string)($_POST["fech_limite"] ?? "");
            if ($titulo === ""){ tarea_error("Escribe el título de la tarea."); }
            if (trim(strip_tags($descrip, "<img>")) === ""){ tarea_error("Escribe las instrucciones de la tarea."); }
            if (html_demasiado_grande($descrip)){ tarea_error("Las instrucciones son demasiado grandes: usa menos imágenes o recórtalas."); }
            if (!(new Prioridad())->existe($prio_id)){ tarea_error("Elige la urgencia."); }
            if ($limite !== ""){
                $f = DateTime::createFromFormat("!Y-m-d", $limite);
                if (!$f || $f->format("Y-m-d") !== $limite){ tarea_error("La fecha límite no es válida."); }
                if ($limite < date("Y-m-d")){ tarea_error("La fecha límite no puede ser anterior a hoy."); }
            }

            /* Solo personas que de verdad pertenecen al grupo */
            $permitidas = array_map("intval", array_column($tarea->personas_grupo($g["area_id"], $g["sede_id"], $yo), "usu_id"));
            $pedidas = array_unique(array_map("intval", (array)($_POST["personas"] ?? array())));
            $personas = array_values(array_intersect($pedidas, $permitidas));
            if (count($personas) === 0){ tarea_error("Elige al menos una persona."); }
            if (count($personas) !== count($pedidas)){ tarea_error("Alguna persona elegida ya no pertenece al área. Recarga la página."); }

            $id = $tarea->crear(array(
                "area_id" => $g["area_id"], "sede_id" => $g["sede_id"], "usu_crea" => $yo,
                "prio_id" => $prio_id, "titulo" => $titulo, "descrip" => $descrip,
                "fech_limite" => $limite === "" ? null : $limite,
            ), $personas);
            tarea_ok(array("tarea_id" => $id, "personas" => count($personas)));
            break;

        /* Detalle: quien dirige ve el avance de todos; la persona asignada, solo el suyo */
        case "detalle":
            $t = $tarea->tarea((int)($_POST["tarea_id"] ?? 0));
            if ($t === null){ auth_denegar(404, "ajax", "La tarea no existe o fue anulada."); }
            $grupos = $tarea->grupos_asignables($yo, es_admin());
            $dirige = $tarea->puede_gestionar($t, $yo, es_admin(), $grupos);
            $avances = $tarea->avances($t["tarea_id"]);
            $mio = null;
            foreach ($avances as $a){ if ((int)$a["usu_id"] === $yo){ $mio = $a; } }
            if (!$dirige && $mio === null){ auth_denegar(403, "ajax", "Esta tarea no está asignada a ti."); }
            $t["tarea_descrip"] = html_limpio($t["tarea_descrip"]);
            echo json_encode(array("tarea" => $t, "dirige" => $dirige, "mio" => $mio, "avances" => $dirige ? $avances : array()));
            break;

        /* La persona asignada: empezar o entregar */
        case "avanzar":
            $nuevo = (string)($_POST["estado"] ?? "");
            $comentario = texto_post("comentario", 2000);
            if ($nuevo === Tarea::ENTREGADA && $comentario === ""){ tarea_error("Escribe un comentario de lo que hiciste."); }
            if (!$tarea->avanzar((int)($_POST["tarea_id"] ?? 0), $yo, $nuevo, $comentario === "" ? null : $comentario)){
                tarea_error("No se pudo actualizar: la tarea no está asignada a ti o ya cambió de estado. Recarga la página.");
            }
            tarea_ok();
            break;

        /* Quien dirige: marcar revisada o devolver con observación */
        case "revisar":
            $t = tarea_gestionable($tarea, (int)($_POST["tarea_id"] ?? 0));
            $decision = (string)($_POST["decision"] ?? "");
            $obs = texto_post("observacion", 2000);
            if ($decision === Tarea::DEVUELTA && $obs === ""){ tarea_error("Escribe qué debe corregir."); }
            if (!$tarea->revisar($t["tarea_id"], (int)($_POST["usu_id"] ?? 0), $decision, $obs === "" ? null : $obs)){
                tarea_error("No se pudo revisar: la entrega ya cambió de estado. Recarga la página.");
            }
            tarea_ok();
            break;

        case "anular":
            $t = tarea_gestionable($tarea, (int)($_POST["tarea_id"] ?? 0));
            $tarea->anular($t["tarea_id"]);
            tarea_ok();
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
