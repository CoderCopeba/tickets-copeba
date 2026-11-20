<?php
    require_once("../config/conexion.php");
    require_once("../models/Usuario.php");
    $usuario = new Usuario();

    switch($_GET["op"]){
        case "guardaryeditar":
            $usu_id      = $_POST["usu_id"] ?? "";
            $usu_usuario = Usuario::normalizar_usuario($_POST["usu_usuario"] ?? "");

            if (!Usuario::usuario_valido($usu_usuario)){
                echo json_encode(array("status" => "error", "message" => "Nombre de usuario no válido. Use de 3 a 30 caracteres: letras sin acentos (a-z), números, punto (.), guion (-) o guion bajo (_)."));
                break;
            }
            if ($usuario->existe_usuario($usu_usuario, $usu_id)){
                echo json_encode(array("status" => "error", "message" => "El nombre de usuario \"".$usu_usuario."\" ya está en uso. Elija otro."));
                break;
            }

            try {
                if(empty($usu_id)){
                    $usuario->insert_usuario($_POST["usu_nom"] ?? "",$_POST["usu_ape"] ?? "",$usu_usuario,$_POST["usu_correo"] ?? "",$_POST["usu_pass"] ?? "",$_POST["rol_id"] ?? 1);
                }
                else {
                    $usuario->update_usuario($usu_id,$_POST["usu_nom"] ?? "",$_POST["usu_ape"] ?? "",$usu_usuario,$_POST["usu_correo"] ?? "",$_POST["usu_pass"] ?? "",$_POST["rol_id"] ?? 1);
                }
            } catch (PDOException $e) {
                /* 23000 = violación de índice único (dos altas simultáneas con el mismo usuario) */
                if ($e->getCode() == 23000){
                    echo json_encode(array("status" => "error", "message" => "El nombre de usuario \"".$usu_usuario."\" ya está en uso. Elija otro."));
                } else {
                    error_log("Error al guardar usuario: ".$e->getMessage());
                    echo json_encode(array("status" => "error", "message" => "No se pudo guardar el usuario."));
                }
                break;
            }
            echo json_encode(array("status" => "ok"));
            break;

        case "listar":
            $datos=$usuario->get_usuario();
            $data= Array();
            foreach($datos as $row){
                $sub_array = array();
                $sub_array[] = $row["usu_nom"];
                $sub_array[] = $row["usu_ape"];
                $sub_array[] = htmlspecialchars((string)($row["usu_usuario"] ?? ""), ENT_QUOTES, "UTF-8");
                $sub_array[] = $row["usu_correo"];
                $sub_array[] = $row["usu_pass"];

                if ($row["rol_id"]=="1"){
                    $sub_array[] = '<span class="label label-pill label-success">Usuario</span>';
                }else{
                    $sub_array[] = '<span class="label label-pill label-info">Soporte</span>';
                }

                $sub_array[] = '<button type="button" onClick="editar('.$row["usu_id"].');"  id="'.$row["usu_id"].'" class="btn btn-inline btn-warning btn-sm ladda-button"><i class="fa fa-edit"></i></button>';
                $sub_array[] = '<button type="button" onClick="eliminar('.$row["usu_id"].');"  id="'.$row["usu_id"].'" class="btn btn-inline btn-danger btn-sm ladda-button"><i class="fa fa-trash"></i></button>';
                $data[] = $sub_array;
            }

            $results = array(
                "sEcho"=>1,
                "iTotalRecords"=>count($data),
                "iTotalDisplayRecords"=>count($data),
                "aaData"=>$data);
            echo json_encode($results);
            break;

        case "eliminar":
            $usuario->delete_usuario($_POST["usu_id"]);
            break;

        case "mostrar";
            $datos=$usuario->get_usuario_x_id($_POST["usu_id"]);  
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $output["usu_id"] = $row["usu_id"];
                    $output["usu_nom"] = $row["usu_nom"];
                    $output["usu_ape"] = $row["usu_ape"];
                    $output["usu_usuario"] = $row["usu_usuario"] ?? "";
                    $output["usu_correo"] = $row["usu_correo"];
                    $output["usu_pass"] = $row["usu_pass"];
                    $output["rol_id"] = $row["rol_id"];
                }
                echo json_encode($output);
            }   
            break;

        case "total";
            $datos=$usuario->get_usuario_total_x_id($_POST["usu_id"]);  
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $output["TOTAL"] = $row["TOTAL"];
                }
                echo json_encode($output);
            }
            break;

        case "totalabierto";
            $datos=$usuario->get_usuario_totalabierto_x_id($_POST["usu_id"]);  
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $output["TOTAL"] = $row["TOTAL"];
                }
                echo json_encode($output);
            }
            break;

        case "totalcerrado";
            $datos=$usuario->get_usuario_totalcerrado_x_id($_POST["usu_id"]);  
            if(is_array($datos)==true and count($datos)>0){
                foreach($datos as $row)
                {
                    $output["TOTAL"] = $row["TOTAL"];
                }
                echo json_encode($output);
            }
            break;

        case "grafico";
            $datos=$usuario->get_usuario_grafico($_POST["usu_id"]);  
            echo json_encode($datos);
            break;

        case "combo";
            $datos = $usuario->get_usuario_x_rol();
            $html = "";
            if(is_array($datos)==true and count($datos)>0){
                $html.= "<option label='Seleccionar'></option>";
                foreach($datos as $row)
                {
                    $html.= "<option value='".$row['usu_id']."'>".$row['usu_nom']."</option>";
                }
                echo $html;
            }
            break;
        /* Controller para actualizar contraseña */
        case "password":
            $usuario->update_usuario_pass($_POST["usu_id"],$_POST["usu_pass"]);
            break;

    }
?>