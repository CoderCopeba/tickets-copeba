<?php
    require_once("../config/conexion.php");
    require_once("../models/Ticket.php");
    $ticket = new Ticket();

    require_once("../models/Usuario.php");
    $usuario = new Usuario();

    require_once("../models/Documento.php");
    $documento = new Documento();
    require_once("../models/Prioridad.php");
    $prioridad = new Prioridad();
    require_once("../models/Area.php");
    $area = new Area();

    /* ===== Adjuntos: reglas ===== */
    const ADJ_MAX_BYTES = 10 * 1024 * 1024; /* 10 MB */
    const ADJ_EXTENSIONES = array("pdf","jpg","jpeg","png","doc","docx","xls","xlsx","txt");

    /* Sanea un nombre de archivo: sin rutas, sin caracteres peligrosos, sin dobles extensiones */
    function adjunto_sanear_nombre($nombre){
        $nombre = basename(str_replace("\\", "/", (string)$nombre));
        $ext  = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $base = pathinfo($nombre, PATHINFO_FILENAME);

        /* Letras (incluye acentos), números, espacio, guion, guion bajo y paréntesis; el resto -> "_" */
        $limpio = preg_replace('/[^\p{L}\p{N} _()\-]/u', '_', $base);
        if ($limpio === null){ /* UTF-8 inválido */
            $limpio = preg_replace('/[^A-Za-z0-9 _()\-]/', '_', $base);
        }
        $limpio = trim(preg_replace('/\s+/', ' ', $limpio), " _-");
        if ($limpio === ""){
            $limpio = "archivo";
        }
        $limpio = mb_substr($limpio, 0, 150, "UTF-8");
        return $limpio.".".$ext;
    }

    /* Devuelve un nombre que no exista ya en la carpeta: "x.pdf", "x (2).pdf", ... */
    function adjunto_nombre_libre($ruta, $nombre){
        $ext  = pathinfo($nombre, PATHINFO_EXTENSION);
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $candidato = $nombre;
        $n = 2;
        while (file_exists($ruta.$candidato)){
            $candidato = $base." (".$n.").".$ext;
            $n++;
        }
        return $candidato;
    }

    /* Valida $_FILES['files']; llena $archivos con [tmp, nombre]. Devuelve null o un mensaje de error */
    function adjuntos_validar(&$archivos){
        $archivos = array();
        if (!isset($_FILES["files"]) || !is_array($_FILES["files"]["name"] ?? null)){
            return null; /* ticket sin adjuntos */
        }
        $f = $_FILES["files"];
        $total = count($f["name"]);
        for ($i = 0; $i < $total; $i++){
            $error  = $f["error"][$i] ?? UPLOAD_ERR_NO_FILE;
            $original = (string)($f["name"][$i] ?? "");
            $visible = htmlspecialchars(basename(str_replace("\\", "/", $original)), ENT_QUOTES, "UTF-8");

            if ($error === UPLOAD_ERR_NO_FILE){
                continue;
            }
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($f["size"][$i] ?? 0) > ADJ_MAX_BYTES){
                return "El archivo ".$visible." supera el máximo de 10 MB.";
            }
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($f["tmp_name"][$i])){
                return "No se pudo subir el archivo ".$visible.".";
            }
            $nombre = adjunto_sanear_nombre($original);
            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            if (!in_array($ext, ADJ_EXTENSIONES, true)){
                return "Tipo de archivo no permitido: ".$visible.". Permitidos: ".implode(", ", ADJ_EXTENSIONES).".";
            }
            $archivos[] = array("tmp" => $f["tmp_name"][$i], "nombre" => $nombre);
        }
        return null;
    }


    /* Guarda los adjuntos ya validados en public/document/<ticket>/ y los registra */
    function adjuntos_guardar($archivos, $tick_id, $tickd_id, $documento){
        if (count($archivos) === 0){ return; }
        $ruta = "../public/document/".(int)$tick_id."/";
        if (!is_dir($ruta)) {
            mkdir($ruta, 0755, true);
        }
        foreach ($archivos as $archivo){
            $nombre = adjunto_nombre_libre($ruta, $archivo["nombre"]);
            if (move_uploaded_file($archivo["tmp"], $ruta.$nombre)){
                $documento->insert_documento($tick_id, $nombre, $tickd_id);
            }else{
                error_log("No se pudo mover el adjunto del ticket ".(int)$tick_id);
            }
        }
    }

    /* Respuestas JSON comunes */
    function ticket_ok(){
        echo json_encode(array("status" => "ok"));
    }
    function ticket_error($mensaje){
        echo json_encode(array("status" => "error", "message" => $mensaje));
        exit();
    }
    function nombre_sesion(){
        return trim(($_SESSION["usu_nom"] ?? "")." ".($_SESSION["usu_ape"] ?? ""));
    }

    /* ===== Ayudantes de salida ===== */
    function ticket_estado_html($estado, $tick_id, $puede_reabrir){
        if ($estado === "Abierto"){
            return '<span class="label label-pill label-success">Abierto</span>';
        }
        if ($estado === "En espera"){
            return '<span class="label label-pill label-warning">En espera</span>';
        }
        if ($puede_reabrir){
            return '<a onClick="CambiarEstado('.(int)$tick_id.')"><span class="label label-pill label-danger">Cerrado</span></a>';
        }
        return '<span class="label label-pill label-danger">Cerrado</span>';
    }

    function ticket_nombre_asignado($usuario, $usu_asig){
        $datos1 = $usuario->get_usuario_x_id($usu_asig);
        $nombre = (is_array($datos1) && count($datos1) > 0) ? $datos1[0]["usu_nom"] : "(desconocido)";
        return '<span class="label label-pill label-success">'.e($nombre).'</span>';
    }

    function ticket_fila($row, $usuario, $con_asignar){
        $id = (int)$row["tick_id"];
        $sub_array = array();
        $sub_array[] = $id;
        $sub_array[] = e($row["cat_nom"]);
        $sub_array[] = e($row["tick_titulo"]);
        $sub_array[] = ticket_estado_html($row["tick_estado"], $id, true);
        $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

        if($row["fech_asig"]==null){
            $sub_array[] = '<span class="label label-pill label-default">Sin Asignar</span>';
        }else{
            $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_asig"]));
        }

        if($row["usu_asig"]==null){
            $sub_array[] = $con_asignar
                ? '<a onClick="asignar('.$id.');"><span class="label label-pill label-warning">Sin Asignar</span></a>'
                : '<span class="label label-pill label-warning">Sin Asignar</span>';
        }else{
            $sub_array[] = ticket_nombre_asignado($usuario, $row["usu_asig"]);
        }

        $sub_array[] = '<a href="../DetalleTicket/?ID='.$id.'" class="btn btn-inline btn-primary btn-sm" title="Ver ticket" aria-label="Ver ticket '.$id.'"><i class="fa fa-eye"></i></a>';
        return $sub_array;
    }

    function ticket_tabla_json($data){
        return json_encode(array(
            "sEcho"=>1,
            "iTotalRecords"=>count($data),
            "iTotalDisplayRecords"=>count($data),
            "aaData"=>$data));
    }

    /* Ninguna operación de este controlador es pública */
    requiere_sesion("ajax");

    $op = $_GET["op"] ?? "";

    switch($op){

        /* ===== Cualquier rol: crear ticket propio ===== */
        case "insert":
            /* Si el envío supera post_max_size, PHP vacía $_POST y $_FILES */
            if (empty($_POST) && isset($_SERVER["CONTENT_LENGTH"]) && (int)$_SERVER["CONTENT_LENGTH"] > 0){
                echo json_encode(array("error" => "El envío es demasiado grande. Máximo 10 MB por archivo."));
                break;
            }

            $tick_titulo  = trim((string)($_POST["tick_titulo"] ?? ""));
            $tick_descrip = html_limpio($_POST["tick_descrip"] ?? "");
            $cat_id       = (int)($_POST["cat_id"] ?? 0);
            $prio_id      = (int)($_POST["prio_id"] ?? Prioridad::NORMAL);
            if (!$prioridad->existe($prio_id)) { $prio_id = Prioridad::NORMAL; }
            if ($tick_titulo === "" || trim(strip_tags($tick_descrip)) === "" || $cat_id <= 0){
                echo json_encode(array("error" => "Complete el título, la categoría y la descripción."));
                break;
            }

            /* 1) Validar adjuntos ANTES de crear el ticket */
            $archivos = array();
            $error_adjunto = adjuntos_validar($archivos);
            if ($error_adjunto !== null){
                echo json_encode(array("error" => $error_adjunto));
                break;
            }

            /* 2) Crear ticket: el creador SIEMPRE es el usuario de la sesión */
            $datos=$ticket->insert_ticket(usuario_actual_id(), $cat_id, $tick_titulo, $tick_descrip, $prio_id);

            /* 3) Guardar adjuntos */
            if (is_array($datos) && count($datos) > 0){
                adjuntos_guardar($archivos, (int)$datos[0]["tick_id"], null, $documento);
            }
            echo json_encode($datos);
        break;

        /* ===== Pantalla de detalle: todo en una petición (propietario o Soporte/Admin) ===== */
        case "detalle":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $t = $ticket->detalle($tick_id);
            if ($t === null){
                auth_denegar(404, "ajax", "El ticket no existe.");
            }
            $soporte = es_soporte();

            /* Adjuntos agrupados: los del ticket (sin entrada) y los de cada respuesta */
            $docs = array("ticket" => array());
            foreach ($documento->get_documento_x_ticket($tick_id, $soporte) as $d){
                $clave = $d["tickd_id"] === null ? "ticket" : "e".(int)$d["tickd_id"];
                $docs[$clave][] = array("id" => (int)$d["doc_id"], "nombre" => $d["doc_nom"]);
            }

            $entradas = array();
            foreach ($ticket->bitacora($tick_id, $soporte) as $b){
                $entradas[] = array(
                    "id"     => (int)$b["tickd_id"],
                    "tipo"   => $b["tickd_tipo"],
                    "html"   => html_limpio($b["tickd_descrip"]),
                    "fecha"  => $b["fech_crea"],
                    "autor"  => trim($b["usu_nom"]." ".$b["usu_ape"]),
                    "rol"    => rol_nombre($b["rol_id"]),
                    "es_ti"  => (bool)$b["es_ti"],
                    "docs"   => $docs["e".(int)$b["tickd_id"]] ?? array(),
                );
            }

            /* Tiempo objetivo (SLA) según la prioridad */
            $limite = strtotime($t["fech_crea"]) + (int)$t["prio_sla_horas"] * 3600;

            echo json_encode(array(
                "ticket" => array(
                    "id"            => (int)$t["tick_id"],
                    "titulo"        => $t["tick_titulo"],
                    "html"          => html_limpio($t["tick_descrip"]),
                    "estado"        => $t["tick_estado"],
                    "fecha"         => $t["fech_crea"],
                    "categoria"     => $t["cat_nom"],
                    "prioridad"     => $t["prio_nom"],
                    "prio_id"       => (int)$t["prio_id"],
                    "sla_horas"     => (int)$t["prio_sla_horas"],
                    "sla_limite"    => date("Y-m-d H:i:s", $limite),
                    "fech_cierre"   => $t["fech_cierre"],
                    "solicitante"   => trim($t["usu_nom"]." ".$t["usu_ape"]),
                    "asignado"      => $t["usu_asig"] ? trim($t["asig_nom"]." ".$t["asig_ape"]) : null,
                    "asignado_a_mi" => (int)$t["usu_asig"] === usuario_actual_id(),
                    "docs"          => $docs["ticket"],
                ),
                "bitacora" => $entradas,
                "ahora"    => date("Y-m-d H:i:s"),
                "permisos" => array(
                    "soporte" => $soporte,
                    "propio"  => (int)$t["usu_id"] === usuario_actual_id(),
                ),
            ));
        break;

        /* ===== Responder (todos) o nota interna (solo TI), con adjuntos ===== */
        case "responder":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $tipo = ($_POST["tipo"] ?? "respuesta") === "nota" ? "nota" : "respuesta";
            if ($tipo === "nota" && !es_soporte()){
                auth_denegar(403, "ajax", "Solo el equipo de TI puede escribir notas internas.");
            }
            $tickd_descrip = html_limpio($_POST["tickd_descrip"] ?? "");
            if (trim(strip_tags($tickd_descrip, "<img>")) === ""){
                ticket_error("Escribe el mensaje antes de enviarlo.");
            }
            $t = $ticket->detalle($tick_id);
            if ($t === null || $t["tick_estado"] === "Cerrado"){
                ticket_error("El ticket está cerrado. Reábrelo para responder.");
            }
            $archivos = array();
            $error_adjunto = adjuntos_validar($archivos);
            if ($error_adjunto !== null){
                ticket_error($error_adjunto);
            }
            /* el autor SIEMPRE es el usuario de la sesión */
            $tickd_id = $ticket->insert_ticketdetalle($tick_id, usuario_actual_id(), $tickd_descrip, $tipo);
            adjuntos_guardar($archivos, $tick_id, $tickd_id, $documento);
            ticket_ok();
        break;

        /* ===== Cerrar / reabrir: propietario o Soporte/Admin ===== */
        case "update":
        case "cerrar":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $ticket->cambiar_estado($tick_id, "Cerrado");
            $ticket->insert_evento($tick_id, usuario_actual_id(), "Ticket cerrado por ".nombre_sesion());
            ticket_ok();
        break;

        case "reabrir":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $ticket->cambiar_estado($tick_id, "Abierto");
            $ticket->insert_evento($tick_id, usuario_actual_id(), "Ticket reabierto por ".nombre_sesion());
            ticket_ok();
        break;

        /* ===== Soporte y Administrador ===== */
        case "asignar":
            requiere_soporte();
            $tick_id  = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $usu_asig = (int)($_POST["usu_asig"] ?? 0);
            if (!$area->es_agente($usu_asig)){
                ticket_error("Solo se puede asignar a alguien de Informática (o un administrador del sistema) activo.");
            }
            $ticket->update_ticket_asignacion($tick_id, $usu_asig);
            $destino = $usuario->get_usuario_x_id($usu_asig);
            $nombre  = trim(($destino[0]["usu_nom"] ?? "")." ".($destino[0]["usu_ape"] ?? ""));
            $ticket->insert_evento($tick_id, usuario_actual_id(), "Ticket asignado a ".$nombre." por ".nombre_sesion());
            ticket_ok();
        break;

        /* Autoasignarse el ticket */
        case "tomar":
            requiere_soporte();
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $ticket->update_ticket_asignacion($tick_id, usuario_actual_id());
            $ticket->insert_evento($tick_id, usuario_actual_id(), nombre_sesion()." tomó el ticket");
            ticket_ok();
        break;

        /* Posponer: queda "En espera" con el motivo en la bitácora */
        case "posponer":
            requiere_soporte();
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $motivo = trim(mb_substr((string)($_POST["motivo"] ?? ""), 0, 300));
            if ($motivo === ""){
                ticket_error("Indica el motivo para posponer el ticket.");
            }
            $t = $ticket->detalle($tick_id);
            if ($t === null || $t["tick_estado"] === "Cerrado"){
                ticket_error("Solo se pueden posponer tickets abiertos.");
            }
            $ticket->cambiar_estado($tick_id, "En espera");
            $ticket->insert_evento($tick_id, usuario_actual_id(), "Ticket pospuesto por ".nombre_sesion().": ".$motivo);
            ticket_ok();
        break;

        case "reanudar":
            requiere_soporte();
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $ticket->cambiar_estado($tick_id, "Abierto");
            $ticket->insert_evento($tick_id, usuario_actual_id(), "Ticket reanudado por ".nombre_sesion());
            ticket_ok();
        break;

        /* ===== Cualquier rol: SUS tickets (id tomado de la sesión) ===== */
        case "listar_x_usu":
            $datos=$ticket->listar_ticket_x_usu(usuario_actual_id());
            $data= Array();
            foreach($datos as $row){
                $data[] = ticket_fila($row, $usuario, false);
            }
            echo ticket_tabla_json($data);
        break;

        /* ===== Soporte y Administrador: todos los tickets ===== */
        case "listar":
            requiere_soporte();
            $datos=$ticket->listar_ticket();
            $data= Array();
            foreach($datos as $row){
                $data[] = ticket_fila($row, $usuario, true);
            }
            echo ticket_tabla_json($data);
        break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
?>
