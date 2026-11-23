<?php
    require_once("../config/conexion.php");
    require_once("../models/Categoria.php");
    $categoria = new Categoria();

    /* Cualquier usuario con sesión (necesario para crear tickets) */
    requiere_sesion("ajax");

    switch($_GET["op"] ?? ""){
        case "combo":
            $datos = $categoria->get_categoria();
            $html = "";
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $html.= "<option value='".(int)$row['cat_id']."'>".e($row['cat_nom'])."</option>";
                }
            }
            echo $html;
        break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
?>
