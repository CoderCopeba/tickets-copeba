<?php
    /* Planificación semanal y mensual. Cada persona solo toca su propio plan;
       la jefatura (Responsable/Suplente de sus grupos, o el Administrador) lo ve y revisa. */
    require_once("../config/conexion.php");
    require_once("../models/Plan.php");
    requiere_sesion("ajax");
    header("Content-Type: application/json; charset=utf-8");
    $plan = new Plan();
    $yo = usuario_actual_id();

    function plan_ok($extra = array()){
        echo json_encode(array_merge(array("status" => "ok"), $extra));
        exit();
    }
    function plan_error($mensaje){
        echo json_encode(array("status" => "error", "message" => $mensaje));
        exit();
    }
    function plan_texto($campo, $max){
        return trim(mb_substr((string)($_POST[$campo] ?? ""), 0, $max));
    }
    /* Periodo pedido (tipo + cualquier fecha dentro de él) */
    function plan_periodo(){
        $p = Plan::periodo((string)($_POST["tipo"] ?? ""), (string)($_POST["fecha"] ?? ""));
        if ($p === null){ plan_error("El periodo no es válido."); }
        return $p;
    }
    /* Día opcional: vacío o una fecha dentro del periodo */
    function plan_dia(array $p){
        $dia = (string)($_POST["dia"] ?? "");
        if ($dia === ""){ return null; }
        $d = DateTime::createFromFormat("!Y-m-d", $dia);
        if (!$d || $d->format("Y-m-d") !== $dia || $dia < $p["inicio"] || $dia > $p["fin"]){
            plan_error("El día debe estar dentro del periodo.");
        }
        return $dia;
    }
    /* Periodo donde va una actividad nueva: el que se está viendo o, si el día elegido
       cae fuera, el periodo (semana o mes) de ese día. Así se puede planificar una salida
       dentro de 15 días sin cambiar de semana. No se permite un día que ya pasó fuera del
       periodo visto. Devuelve [periodo, dia|null]. */
    function plan_destino(){
        $p = plan_periodo();
        $dia = (string)($_POST["dia"] ?? "");
        if ($dia === ""){ return array($p, null); }
        $d = DateTime::createFromFormat("!Y-m-d", $dia);
        if (!$d || $d->format("Y-m-d") !== $dia){ plan_error("El día no es válido."); }
        if ($dia >= $p["inicio"] && $dia <= $p["fin"]){ return array($p, $dia); }
        if ($dia < date("Y-m-d")){ plan_error("Ese día ya pasó: elige hoy o un día próximo."); }
        return array(Plan::periodo((string)($_POST["tipo"] ?? ""), $dia), $dia);
    }
    /* Actividad propia y vigente; si no, 403/404 */
    function plan_actividad_mia(Plan $plan, $act_id){
        $a = $plan->actividad($act_id);
        if ($a === null){ auth_denegar(404, "ajax", "La actividad no existe."); }
        if ((int)$a["usu_id"] !== usuario_actual_id()){ auth_denegar(403, "ajax", "Solo puedes cambiar tu propio plan."); }
        return $a;
    }
    function plan_dirige(Plan $plan){
        return es_admin() || count((new Tarea())->grupos_asignables(usuario_actual_id(), false)) > 0;
    }
    /* Plan + actividades listo para la pantalla */
    function plan_datos(Plan $plan, $fila, array $p){
        if ($fila === null){ return null; }
        $fila["tarde"] = Plan::tarde($fila["fech_entrega"], $p["plazo"]);
        $fila["actividades"] = $plan->actividades($fila["plan_id"]);
        return $fila;
    }

    switch ($_GET["op"] ?? "") {

        /* Mis planes de todas las semanas (o meses) de un rango Desde–Hasta.
           Pocas consultas en total: planes del rango + actividades de todos ellos. */
        case "rango":
            $tipo = (string)($_POST["tipo"] ?? "");
            $periodos = Plan::periodos($tipo, (string)($_POST["desde"] ?? ""), (string)($_POST["hasta"] ?? ""));
            if ($periodos === null){ plan_error("Revisa el rango: fechas válidas, Desde antes que Hasta y como máximo 12 ".($tipo === Plan::MES ? "meses." : "semanas.")); }
            $ultimo = end($periodos);
            $planes = $plan->planes_rango($yo, $tipo, $periodos[0]["inicio"], $ultimo["inicio"]);
            $acts = $plan->actividades_de(array_map(function ($f){ return (int)$f["plan_id"]; }, array_values($planes)));
            $bloques = array();
            foreach ($periodos as $p){
                $fila = $planes[$p["inicio"]] ?? null;
                if ($fila !== null){
                    $fila["tarde"] = Plan::tarde($fila["fech_entrega"], $p["plazo"]);
                    $fila["actividades"] = $acts[(int)$fila["plan_id"]] ?? array();
                }
                $bloques[] = array("periodo" => $p, "plan" => $fila);
            }
            echo json_encode(array(
                "bloques" => $bloques,
                "hoy"     => date("Y-m-d"),
                "dirige"  => plan_dirige($plan),
                "soporte" => es_soporte(),
            ));
            break;

        /* Jefatura: planes de su gente en todos los periodos del rango (una fila por persona y periodo) */
        case "equipo_rango":
            if (!plan_dirige($plan)){ auth_denegar(403, "ajax", "Solo la jefatura de un área ve los planes del equipo."); }
            $tipo = (string)($_POST["tipo"] ?? "");
            $periodos = Plan::periodos($tipo, (string)($_POST["desde"] ?? ""), (string)($_POST["hasta"] ?? ""));
            if ($periodos === null){ plan_error("Revisa el rango de fechas."); }
            $ultimo = end($periodos);
            $personas = $plan->personas_a_cargo($yo, es_admin());
            $resumen = $plan->resumen_equipo_rango(array_column($personas, "usu_id"), $tipo, $periodos[0]["inicio"], $ultimo["inicio"]);
            $hoy = date("Y-m-d");
            $filas = array();
            foreach ($periodos as $p){
                foreach ($personas as $x){
                    $r = $resumen[$x["usu_id"]][$p["inicio"]] ?? null;
                    $x["periodo"] = $p;
                    $x["plan_id"] = $r ? (int)$r["plan_id"] : null;
                    $x["estado"]  = $r ? $r["plan_estado"] : "sin_plan";
                    $x["tarde"]   = $r ? (Plan::tarde($r["fech_entrega"], $p["plazo"]) || ($r["plan_estado"] === "borrador" && $hoy > $p["plazo"]))
                                       : $hoy > $p["plazo"];
                    foreach (array("total", "hechas", "no_cumplidas", "pospuestas") as $k){ $x[$k] = $r ? (int)$r[$k] : 0; }
                    $filas[] = $x;
                }
            }
            echo json_encode(array("hoy" => $hoy, "personas" => $filas));
            break;

        /* Quien atiende tickets: tickets abiertos suyos o sin técnico, para planificarlos */
        case "tickets":
            requiere_soporte();
            echo json_encode($plan->tickets_disponibles($yo));
            break;

        /* Solo TI: notas internas recientes de los tickets, para tenerlas en cuenta al planificar */
        case "notas":
            requiere_soporte();
            echo json_encode($plan->notas_internas());
            break;

        /* Trae un ticket al plan (p. ej. "Viaje a Soloma" para el de las cámaras).
           Si no tenía técnico, quien lo planifica lo toma y queda en la bitácora. */
        case "agregar_ticket":
            requiere_soporte();
            list($p, $dia) = plan_destino();
            $tk = $plan->ticket_planificable((int)($_POST["tick_id"] ?? 0), $yo);
            if ($tk === null){ plan_error("El ticket ya está cerrado o lo tiene otro técnico. Recarga la lista."); }
            $texto = plan_texto("texto", 300);
            if ($texto === ""){ $texto = mb_substr($tk["tick_titulo"], 0, 300); }
            $plan_id = $plan->asegurar_plan($yo, $p);
            $actual = $plan->plan($plan_id);
            if ($actual["plan_estado"] === "revisado"){ plan_error("El plan ya fue revisado: no se le pueden agregar actividades."); }
            if ($tk["usu_asig"] === null){
                require_once("../models/Ticket.php");
                $ticket = new Ticket();
                $ticket->update_ticket_asignacion($tk["tick_id"], $yo);
                $nombre = trim(($_SESSION["usu_nom"] ?? "")." ".($_SESSION["usu_ape"] ?? ""));
                $ticket->insert_evento($tk["tick_id"], $yo, $nombre." tomó el ticket y lo planificó".($dia ? " para el ".date("d/m/Y", strtotime($dia)) : ""));
            }
            $plan->agregar($plan_id, $texto, $dia, $actual["plan_estado"] !== "borrador", null, $tk["tick_id"]);
            plan_ok(array("tomado" => $tk["usu_asig"] === null, "periodo" => $p));
            break;

        /* Actividad a partir de una nota interna de TI: queda con la referencia
           "Nota interna 004 · <actividad>" (no toma el ticket ni lo vincula). */
        case "agregar_nota":
            requiere_soporte();
            list($p, $dia) = plan_destino();
            $nota = $plan->nota((int)($_POST["tickd_id"] ?? 0));
            if ($nota === null){ plan_error("La nota ya no existe. Recarga la lista."); }
            $texto = plan_texto("texto", 250);
            if ($texto === ""){ plan_error("Escribe la actividad, p. ej. Salida por suministros."); }
            $plan_id = $plan->asegurar_plan($yo, $p);
            $actual = $plan->plan($plan_id);
            if ($actual["plan_estado"] === "revisado"){ plan_error("El plan ya fue revisado: no se le pueden agregar actividades."); }
            $referencia = "Nota interna ".Plan::folio_nota($nota["tickd_id"])." · ";
            $plan->agregar($plan_id, mb_substr($referencia.$texto, 0, 300), $dia, $actual["plan_estado"] !== "borrador");
            plan_ok(array("periodo" => $p));
            break;

        case "agregar":
            list($p, $dia) = plan_destino();
            $texto = plan_texto("texto", 300);
            if ($texto === ""){ plan_error("Escribe la actividad."); }
            $plan_id = $plan->asegurar_plan($yo, $p);
            $actual = $plan->plan($plan_id);
            if ($actual["plan_estado"] === "revisado"){ plan_error("El plan ya fue revisado: no se le pueden agregar actividades."); }
            /* Agregada después de entregar = imprevisto (queda marcada) */
            $plan->agregar($plan_id, $texto, $dia, $actual["plan_estado"] !== "borrador");
            plan_ok(array("periodo" => $p));
            break;

        case "editar":
            $a = plan_actividad_mia($plan, (int)($_POST["act_id"] ?? 0));
            if ($a["plan_estado"] !== "borrador"){ plan_error("El plan ya fue entregado: solo puedes marcar el avance."); }
            $texto = plan_texto("texto", 300);
            if ($texto === ""){ plan_error("Escribe la actividad."); }
            $p = Plan::periodo($a["plan_tipo"], $a["fech_inicio"]);
            $plan->editar($a["act_id"], $texto, plan_dia($p));
            plan_ok();
            break;

        case "quitar":
            $a = plan_actividad_mia($plan, (int)($_POST["act_id"] ?? 0));
            if ($a["plan_estado"] !== "borrador"){ plan_error("El plan ya fue entregado: no se pueden quitar actividades."); }
            $plan->quitar($a["act_id"]);
            plan_ok();
            break;

        case "entregar":
            $p = plan_periodo();
            $fila = $plan->plan_de($yo, $p);
            if ($fila === null || !count($plan->actividades($fila["plan_id"]))){ plan_error("Agrega al menos una actividad antes de entregar."); }
            if (!$plan->entregar($fila["plan_id"])){ plan_error("El plan ya estaba entregado."); }
            plan_ok(array("tarde" => date("Y-m-d") > $p["plazo"]));
            break;

        /* Hecho, no cumplido (con justificación) o volver a pendiente */
        case "marcar":
            $a = plan_actividad_mia($plan, (int)($_POST["act_id"] ?? 0));
            if ($a["plan_estado"] === "borrador"){ plan_error("Primero entrega el plan."); }
            if ($a["plan_estado"] === "revisado"){ plan_error("El plan ya fue revisado: no se puede cambiar."); }
            if ($a["act_estado"] === "pospuesto"){ plan_error("Esta actividad ya se pospuso a otra fecha."); }
            $estado = (string)($_POST["estado"] ?? "");
            if (!in_array($estado, array("hecho", "no_cumplido", "pendiente"), true)){ plan_error("Estado no válido."); }
            $just = plan_texto("justificacion", 1000);
            if ($estado === "no_cumplido" && $just === ""){ plan_error("Explica por qué no se cumplió."); }
            $plan->marcar($a["act_id"], $estado, $estado === "no_cumplido" ? $just : null);
            plan_ok();
            break;

        /* Pospone con justificación: pasa sola al plan del periodo de la nueva fecha */
        case "posponer":
            $a = plan_actividad_mia($plan, (int)($_POST["act_id"] ?? 0));
            if ($a["plan_estado"] === "borrador"){ plan_error("Primero entrega el plan."); }
            if ($a["plan_estado"] === "revisado"){ plan_error("El plan ya fue revisado: no se puede cambiar."); }
            if ($a["act_estado"] !== "pendiente"){ plan_error("Solo se pueden posponer actividades pendientes."); }
            $just = plan_texto("justificacion", 1000);
            if ($just === ""){ plan_error("Explica por qué se pospone."); }
            $nueva = (string)($_POST["nueva_fecha"] ?? "");
            $d = DateTime::createFromFormat("!Y-m-d", $nueva);
            if (!$d || $d->format("Y-m-d") !== $nueva){ plan_error("Elige la nueva fecha."); }
            if ($nueva < date("Y-m-d")){ plan_error("La nueva fecha no puede ser anterior a hoy."); }
            if ($nueva === $a["act_dia"]){ plan_error("Elige una fecha distinta a la actual."); }
            $destino = $plan->posponer($a, $just, $nueva);
            plan_ok(array("destino" => $destino));
            break;

        /* Jefatura: ver el plan de una persona */
        case "ver_plan":
            $fila = $plan->plan((int)($_POST["plan_id"] ?? 0));
            if ($fila === null){ auth_denegar(404, "ajax", "El plan no existe."); }
            if ((int)$fila["usu_id"] !== $yo && !$plan->puede_supervisar($yo, es_admin(), $fila["usu_id"])){
                auth_denegar(403, "ajax", "No diriges el área de esta persona.");
            }
            $p = Plan::periodo($fila["plan_tipo"], $fila["fech_inicio"]);
            echo json_encode(array("periodo" => $p, "plan" => plan_datos($plan, $fila, $p),
                                   "puede_revisar" => (int)$fila["usu_id"] !== $yo));
            break;

        case "revisar":
            $fila = $plan->plan((int)($_POST["plan_id"] ?? 0));
            if ($fila === null){ auth_denegar(404, "ajax", "El plan no existe."); }
            if ((int)$fila["usu_id"] === $yo || !$plan->puede_supervisar($yo, es_admin(), $fila["usu_id"])){
                auth_denegar(403, "ajax", "Solo la jefatura del área puede revisar este plan.");
            }
            if ($fila["plan_estado"] === "borrador"){ plan_error("La persona todavía no ha entregado su plan."); }
            $plan->revisar($fila["plan_id"], $yo, plan_texto("comentario", 1000) ?: null);
            plan_ok();
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
