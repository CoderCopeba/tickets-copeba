<?php
    /* llamada a las clases necesarias */
    require_once("../config/conexion.php");
    require_once("../models/Documento.php");
    $documento = new Documento();

    /* opciones del controlador */
    switch($_GET["op"]){
        /* manejo de json para poder listar en el datatable, formato de json segun documentacion */
        case "listar":
            $tick_id = isset($_POST["tick_id"]) ? (int)$_POST["tick_id"] : 0;
            $datos=$documento->get_documento_x_ticket($tick_id);
            $data= Array();
            foreach($datos as $row){
                $sub_array = array();
                /* URL codificada (nombres con espacios o paréntesis) y texto escapado; compatible con los nombres antiguos */
                $url = '../../public/document/'.$tick_id.'/'.rawurlencode($row["doc_nom"]);
                $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                $nombre = htmlspecialchars($row["doc_nom"], ENT_QUOTES, 'UTF-8');
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
    }
?>
