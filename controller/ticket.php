<?php
    require_once("../config/conexion.php");
    require_once("../models/Ticket.php");
    $ticket = new Ticket();

    require_once("../models/Usuario.php");
    $usuario = new Usuario();

    require_once("../models/Documento.php");
    $documento = new Documento();

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

    /* ===== Ayudantes de salida ===== */
    function ticket_estado_html($estado, $tick_id, $puede_reabrir){
        if ($estado === "Abierto"){
            return '<span class="label label-pill label-success">Abierto</span>';
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

        $sub_array[] = '<button type="button" onClick="ver('.$id.');"  id="'.$id.'" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></button>';
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
            $datos=$ticket->insert_ticket(usuario_actual_id(), $cat_id, $tick_titulo, $tick_descrip);

            /* 3) Guardar adjuntos */
            if (is_array($datos)==true and count($datos)>0 and count($archivos)>0){
                $tick_id = (int)$datos[0]["tick_id"];
                $ruta = "../public/document/".$tick_id."/";

                if (!is_dir($ruta)) {
                    mkdir($ruta, 0755, true);
                }

                foreach ($archivos as $archivo){
                    $nombre = adjunto_nombre_libre($ruta, $archivo["nombre"]);
                    if (move_uploaded_file($archivo["tmp"], $ruta.$nombre)){
                        $documento->insert_documento($tick_id, $nombre);
                    }else{
                        error_log("No se pudo mover el adjunto del ticket ".$tick_id);
                    }
                }
            }
            echo json_encode($datos);
        break;

        /* ===== Cerrar / reabrir: propietario o Soporte/Admin ===== */
        case "update":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $ticket->update_ticket($tick_id);
            $ticket->insert_ticketdetalle_cerrar($tick_id, usuario_actual_id());
            echo json_encode(array("status" => "ok"));
        break;

        case "reabrir":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $ticket->reabrir_ticket($tick_id);
            $ticket->insert_ticketdetalle_reabrir($tick_id, usuario_actual_id());
            echo json_encode(array("status" => "ok"));
        break;

        /* ===== Soporte y Administrador ===== */
        case "asignar":
            requiere_rol(ROLES_SOPORTE);
            $tick_id  = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $usu_asig = (int)($_POST["usu_asig"] ?? 0);
            if (!$usuario->es_asignable($usu_asig)){
                echo json_encode(array("status" => "error", "message" => "Solo se puede asignar a un usuario de Soporte o Administrador activo."));
                break;
            }
            $ticket->update_ticket_asignacion($tick_id, $usu_asig);
            echo json_encode(array("status" => "ok"));
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
            requiere_rol(ROLES_SOPORTE);
            $datos=$ticket->listar_ticket();
            $data= Array();
            foreach($datos as $row){
                $data[] = ticket_fila($row, $usuario, true);
            }
            echo ticket_tabla_json($data);
        break;

        /* ===== Propietario o Soporte/Admin ===== */
        case "listardetalle":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $datos=$ticket->listar_ticketdetalle_x_ticket($tick_id);
            foreach($datos as $row){
                ?>
                    <article class="activity-line-item box-typical">
                        <div class="activity-line-date">
                            <?php echo date("d/m/Y", strtotime($row["fech_crea"]));?>
                        </div>
                        <header class="activity-line-item-header">
                            <div class="activity-line-item-user">
                                <div class="activity-line-item-user-photo">
                                    <a href="#">
                                        <img src="../../public/<?php echo e(rol_imagen($row['rol_id'])); ?>" alt="">
                                    </a>
                                </div>
                                <div class="activity-line-item-user-name"><?php echo e($row['usu_nom'].' '.$row['usu_ape']);?></div>
                                <div class="activity-line-item-user-status"><?php echo e(rol_nombre($row['rol_id'])); ?></div>
                            </div>
                        </header>
                        <div class="activity-line-action-list">
                            <section class="activity-line-action">
                                <div class="time"><?php echo date("H:i:s", strtotime($row["fech_crea"]));?></div>
                                <div class="cont">
                                    <div class="cont-in">
                                        <p>
                                            <?php echo html_limpio($row["tickd_descrip"]); /* lista blanca también al mostrar */ ?>
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </article>
                <?php
            }
        break;

        case "mostrar";
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $datos=$ticket->listar_ticket_x_id($tick_id);
            if(is_array($datos)==true and count($datos)>0){
                $output = array();
                foreach($datos as $row)
                {
                    $output["tick_id"] = $row["tick_id"];
                    $output["usu_id"] = $row["usu_id"];
                    $output["cat_id"] = $row["cat_id"];

                    /* titulo y cat_nom se usan con .val() (texto); descripción: HTML filtrado */
                    $output["tick_titulo"] = $row["tick_titulo"];
                    $output["tick_descrip"] = html_limpio($row["tick_descrip"]);

                    $output["tick_estado"] = ticket_estado_html($row["tick_estado"], $row["tick_id"], false);
                    $output["tick_estado_texto"] = $row["tick_estado"];

                    $output["fech_crea"] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));
                    /* se insertan con .html(): escapar */
                    $output["usu_nom"] = e($row["usu_nom"]);
                    $output["usu_ape"] = e($row["usu_ape"]);
                    $output["cat_nom"] = $row["cat_nom"];
                }
                echo json_encode($output);
            }
        break;

        case "insertdetalle":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0);
            $tickd_descrip = html_limpio($_POST["tickd_descrip"] ?? "");
            if (trim(strip_tags($tickd_descrip, "<img>")) === ""){
                echo json_encode(array("status" => "error", "message" => "Falta la descripción."));
                break;
            }
            $actual = $ticket->listar_ticket_x_id($tick_id);
            if (($actual[0]["tick_estado"] ?? "") !== "Abierto"){
                echo json_encode(array("status" => "error", "message" => "El ticket está cerrado."));
                break;
            }
            /* el autor de la respuesta SIEMPRE es el usuario de la sesión */
            $ticket->insert_ticketdetalle($tick_id, usuario_actual_id(), $tickd_descrip);
            echo json_encode(array("status" => "ok"));
        break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
?>
