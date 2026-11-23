<?php
    /* llamada a las clases necesarias */
    require_once("../config/conexion.php");
    require_once("../models/Documento.php");
    require_once("../models/Ticket.php");
    $documento = new Documento();
    $ticket = new Ticket();

    $op = $_GET["op"] ?? "";

    switch($op){
        /* ===== Lista de documentos del ticket: propietario o Soporte/Admin ===== */
        case "listar":
            $tick_id = requiere_acceso_ticket($ticket, $_POST["tick_id"] ?? 0, "ajax");
            $datos=$documento->get_documento_x_ticket($tick_id);
            $data= Array();
            foreach($datos as $row){
                $sub_array = array();
                /* Los archivos se sirven a través de op=ver, que vuelve a comprobar el permiso */
                $url = e('../../controller/documento.php?op=ver&doc_id='.(int)$row["doc_id"]);
                $nombre = e($row["doc_nom"]);
                $sub_array[] = '<a href="'.$url.'" target="_blank" rel="noopener">'.$nombre.'</a>';
                $sub_array[] = '<a type="button" href="'.$url.'" target="_blank" rel="noopener" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></a>';
                $data[] = $sub_array;
            }

            $results = array(
                "sEcho"=>1,
                "iTotalRecords"=>count($data),
                "iTotalDisplayRecords"=>count($data),
                "aaData"=>$data);
            echo json_encode($results);
        break;

        /* ===== Ver / descargar un documento: propietario o Soporte/Admin =====
           Se abre en una pestaña nueva, por eso responde como vista (403/404 en HTML). */
        case "ver":
            requiere_sesion("vista");
            $doc = $documento->get_documento_x_id((int)($_GET["doc_id"] ?? 0));
            if ($doc === null){
                auth_denegar(404, "vista", "El documento no existe.");
            }
            requiere_acceso_ticket($ticket, $doc["tick_id"], "vista");

            /* nombre guardado en BD -> solo el nombre, nunca una ruta */
            $nombre = basename(str_replace("\\", "/", (string)$doc["doc_nom"]));
            $carpeta = realpath(__DIR__."/../public/document/".(int)$doc["tick_id"]);
            $archivo = ($carpeta !== false) ? realpath($carpeta.DIRECTORY_SEPARATOR.$nombre) : false;
            if ($archivo === false || strpos($archivo, $carpeta.DIRECTORY_SEPARATOR) !== 0 || !is_file($archivo)){
                auth_denegar(404, "vista", "El archivo ya no está disponible en el servidor.");
            }

            $tipos = array(
                "pdf" => "application/pdf", "jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png",
                "txt" => "text/plain; charset=utf-8",
                "doc" => "application/msword",
                "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                "xls" => "application/vnd.ms-excel",
                "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            );
            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            $tipo = $tipos[$ext] ?? "application/octet-stream";
            $en_linea = in_array($ext, array("pdf", "jpg", "jpeg", "png", "txt"), true);

            header("Content-Type: ".$tipo);
            header("X-Content-Type-Options: nosniff");
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
            header("Content-Length: ".filesize($archivo));
            $ascii = preg_replace('/[^A-Za-z0-9 ._()\-]/', '_', $nombre);
            header('Content-Disposition: '.($en_linea ? "inline" : "attachment").'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($nombre));
            readfile($archivo);
            exit();

        default:
            requiere_sesion("ajax");
            auth_denegar(404, "ajax", "Operación no válida.");
    }
?>
