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

    switch($_GET["op"]){

        case "insert":
            /* Si el envío supera post_max_size, PHP vacía $_POST y $_FILES */
            if (empty($_POST) && isset($_SERVER["CONTENT_LENGTH"]) && (int)$_SERVER["CONTENT_LENGTH"] > 0){
                echo json_encode(array("error" => "El envío es demasiado grande. Máximo 10 MB por archivo."));
                break;
            }

            /* 1) Validar adjuntos ANTES de crear el ticket */
            $archivos = array();
            $error_adjunto = adjuntos_validar($archivos);
            if ($error_adjunto !== null){
                echo json_encode(array("error" => $error_adjunto));
                break;
            }

            /* 2) Crear ticket */
            $datos=$ticket->insert_ticket($_POST["usu_id"] ?? null,$_POST["cat_id"] ?? null,$_POST["tick_titulo"] ?? "",$_POST["tick_descrip"] ?? "");

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

        case "update":
            $ticket->update_ticket($_POST["tick_id"]);
            $ticket->insert_ticketdetalle_cerrar($_POST["tick_id"],$_POST["usu_id"]);
        break;

        case "reabrir":
            $ticket->reabrir_ticket($_POST["tick_id"]);
            $ticket->insert_ticketdetalle_reabrir($_POST["tick_id"],$_POST["usu_id"]);
        break;

        case "asignar":
            $ticket->update_ticket_asignacion($_POST["tick_id"],$_POST["usu_asig"]);
        break;

        case "listar_x_usu":
            $datos=$ticket->listar_ticket_x_usu($_POST["usu_id"]);
            $data= Array();
            foreach($datos as $row){
                $sub_array = array();
                $sub_array[] = $row["tick_id"];
                $sub_array[] = $row["cat_nom"];
                $sub_array[] = $row["tick_titulo"];

                if ($row["tick_estado"]=="Abierto"){
                    $sub_array[] = '<span class="label label-pill label-success">Abierto</span>';
                }else{
                    $sub_array[] = '<a onClick="CambiarEstado('.$row["tick_id"].')"><span class="label label-pill label-danger">Cerrado</span></a>';
                }

                $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

                if($row["fech_asig"]==null){
                    $sub_array[] = '<span class="label label-pill label-default">Sin Asignar</span>';
                }else{
                    $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_asig"]));
                }

                if($row["usu_asig"]==null){
                    $sub_array[] = '<span class="label label-pill label-warning">Sin Asignar</span>';
                }else{
                    $datos1=$usuario->get_usuario_x_id($row["usu_asig"]);
                    foreach($datos1 as $row1){
                        $sub_array[] = '<span class="label label-pill label-success">'. $row1["usu_nom"].'</span>';
                    }
                }

                $sub_array[] = '<button type="button" onClick="ver('.$row["tick_id"].');"  id="'.$row["tick_id"].'" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></button>';
                $data[] = $sub_array;
            }

            $results = array(
                "sEcho"=>1,
                "iTotalRecords"=>count($data),
                "iTotalDisplayRecords"=>count($data),
                "aaData"=>$data);
            echo json_encode($results);
        break;

        case "listar":
            $datos=$ticket->listar_ticket();
            $data= Array();
            foreach($datos as $row){
                $sub_array = array();
                $sub_array[] = $row["tick_id"];
                $sub_array[] = $row["cat_nom"];
                $sub_array[] = $row["tick_titulo"];

                if ($row["tick_estado"]=="Abierto"){
                    $sub_array[] = '<span class="label label-pill label-success">Abierto</span>';
                }else{
                    $sub_array[] = '<a onClick="CambiarEstado('.$row["tick_id"].')"><span class="label label-pill label-danger">Cerrado</span><a>';
                }

                $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));

                if($row["fech_asig"]==null){
                    $sub_array[] = '<span class="label label-pill label-default">Sin Asignar</span>';
                }else{
                    $sub_array[] = date("d/m/Y H:i:s", strtotime($row["fech_asig"]));
                }

                if($row["usu_asig"]==null){
                    $sub_array[] = '<a onClick="asignar('.$row["tick_id"].');"><span class="label label-pill label-warning">Sin Asignar</span></a>';
                }else{
                    $datos1=$usuario->get_usuario_x_id($row["usu_asig"]);
                    foreach($datos1 as $row1){
                        $sub_array[] = '<span class="label label-pill label-success">'. $row1["usu_nom"].'</span>';
                    }
                }

                $sub_array[] = '<button type="button" onClick="ver('.$row["tick_id"].');"  id="'.$row["tick_id"].'" class="btn btn-inline btn-primary btn-sm ladda-button"><i class="fa fa-eye"></i></button>';
                $data[] = $sub_array;
            }

            $results = array(
                "sEcho"=>1,
                "iTotalRecords"=>count($data),
                "iTotalDisplayRecords"=>count($data),
                "aaData"=>$data);
            echo json_encode($results);
        break;

        case "listardetalle":
            $datos=$ticket->listar_ticketdetalle_x_ticket($_POST["tick_id"]);
            ?>
                <?php
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
                                                <img src="../../public/<?php echo $row['rol_id'] ?>.jpg" alt="">
                                            </a>
                                        </div>
                                        <div class="activity-line-item-user-name"><?php echo $row['usu_nom'].' '.$row['usu_ape'];?></div>
                                        <div class="activity-line-item-user-status">
                                            <?php 
                                                if ($row['rol_id']==1){
                                                    echo 'Usuario';
                                                }else{
                                                    echo 'Soporte';
                                                }
                                            ?>
                                        </div>
                                    </div>
                                </header>
                                <div class="activity-line-action-list">
                                    <section class="activity-line-action">
                                        <div class="time"><?php echo date("H:i:s", strtotime($row["fech_crea"]));?></div>
                                        <div class="cont">
                                            <div class="cont-in">
                                                <p>
                                                    <?php echo $row["tickd_descrip"];?>
                                                </p>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </article>
                        <?php
                    }
                ?>
            <?php
        break;

        case "mostrar";
            $datos=$ticket->listar_ticket_x_id($_POST["tick_id"]);  
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $output["tick_id"] = $row["tick_id"];
                    $output["usu_id"] = $row["usu_id"];
                    $output["cat_id"] = $row["cat_id"];

                    $output["tick_titulo"] = $row["tick_titulo"];
                    $output["tick_descrip"] = $row["tick_descrip"];

                    if ($row["tick_estado"]=="Abierto"){
                        $output["tick_estado"] = '<span class="label label-pill label-success">Abierto</span>';
                    }else{
                        $output["tick_estado"] = '<span class="label label-pill label-danger">Cerrado</span>';
                    }

                    $output["tick_estado_texto"] = $row["tick_estado"];

                    $output["fech_crea"] = date("d/m/Y H:i:s", strtotime($row["fech_crea"]));
                    $output["usu_nom"] = $row["usu_nom"];
                    $output["usu_ape"] = $row["usu_ape"];
                    $output["cat_nom"] = $row["cat_nom"];
                }
                echo json_encode($output);
            }   
        break;

        case "insertdetalle":
            $ticket->insert_ticketdetalle($_POST["tick_id"],$_POST["usu_id"],$_POST["tickd_descrip"]);
        break;

        case "total";
            $datos=$ticket->get_ticket_total();  
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $output["TOTAL"] = $row["TOTAL"];
                }
                echo json_encode($output);
            }
        break;

        case "totalabierto";
            $datos=$ticket->get_ticket_totalabierto();  
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $output["TOTAL"] = $row["TOTAL"];
                }
                echo json_encode($output);
            }
        break;

        case "totalcerrado";
            $datos=$ticket->get_ticket_totalcerrado();  
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $output["TOTAL"] = $row["TOTAL"];
                }
                echo json_encode($output);
            }
        break;

        case "grafico";
            $datos=$ticket->get_ticket_grafico();  
            echo json_encode($datos);
        break;

    }
?>