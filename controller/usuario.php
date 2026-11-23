<?php
    require_once("../config/conexion.php");
    require_once("../models/Usuario.php");
    $usuario = new Usuario();

    /* Ninguna operación de este controlador es pública */
    requiere_sesion("ajax");

    function json_error($mensaje){
        echo json_encode(array("status" => "error", "message" => $mensaje));
    }

    $op = $_GET["op"] ?? "";

    switch($op){
        /* ===== Solo Administrador ===== */
        case "guardaryeditar":
            requiere_rol(array(ROL_ADMIN));

            $usu_id      = (string)($_POST["usu_id"] ?? "");
            $usu_usuario = Usuario::normalizar_usuario($_POST["usu_usuario"] ?? "");
            $usu_pass    = (string)($_POST["usu_pass"] ?? "");
            $rol_id      = (int)($_POST["rol_id"] ?? 0);
            $es_alta     = ($usu_id === "");

            if (!Usuario::usuario_valido($usu_usuario)){
                json_error("Nombre de usuario no válido. Use de 3 a 30 caracteres: letras sin acentos (a-z), números, punto (.), guion (-) o guion bajo (_).");
                break;
            }
            if (!in_array($rol_id, ROLES_VALIDOS, true)){
                json_error("Rol no válido.");
                break;
            }
            if ($es_alta && $usu_pass === ""){
                json_error("La contraseña es obligatoria para un usuario nuevo.");
                break;
            }
            if (!$es_alta && (int)$usu_id === usuario_actual_id() && $rol_id !== ROL_ADMIN){
                json_error("No puede quitarse a sí mismo el rol de Administrador.");
                break;
            }
            if ($usuario->existe_usuario($usu_usuario, $es_alta ? null : $usu_id)){
                json_error("El nombre de usuario \"".$usu_usuario."\" ya está en uso. Elija otro.");
                break;
            }

            try {
                if($es_alta){
                    $usuario->insert_usuario($_POST["usu_nom"] ?? "",$_POST["usu_ape"] ?? "",$usu_usuario,$_POST["usu_correo"] ?? "",$usu_pass,$rol_id);
                }
                else {
                    /* contraseña vacía = no cambiarla */
                    $usuario->update_usuario((int)$usu_id,$_POST["usu_nom"] ?? "",$_POST["usu_ape"] ?? "",$usu_usuario,$_POST["usu_correo"] ?? "",$usu_pass,$rol_id);
                }
            } catch (PDOException $e) {
                /* 23000 = violación de índice único (dos altas simultáneas con el mismo usuario) */
                if ($e->getCode() == 23000){
                    json_error("El nombre de usuario \"".$usu_usuario."\" ya está en uso. Elija otro.");
                } else {
                    error_log("Error al guardar usuario: ".$e->getMessage());
                    json_error("No se pudo guardar el usuario.");
                }
                break;
            }
            echo json_encode(array("status" => "ok"));
            break;

        case "listar":
            requiere_rol(array(ROL_ADMIN));
            $datos=$usuario->get_usuario();
            $data= Array();
            foreach($datos as $row){
                $sub_array = array();
                $sub_array[] = e($row["usu_nom"]);
                $sub_array[] = e($row["usu_ape"]);
                $sub_array[] = e($row["usu_usuario"] ?? "");
                $sub_array[] = e($row["usu_correo"]);

                switch ((int)$row["rol_id"]){
                    case ROL_USUARIO: $sub_array[] = '<span class="label label-pill label-success">Usuario</span>'; break;
                    case ROL_SOPORTE: $sub_array[] = '<span class="label label-pill label-info">Soporte</span>'; break;
                    case ROL_ADMIN:   $sub_array[] = '<span class="label label-pill label-danger">Administrador</span>'; break;
                    default:          $sub_array[] = '<span class="label label-pill label-default">Desconocido</span>';
                }

                $id = (int)$row["usu_id"];
                $sub_array[] = '<button type="button" onClick="editar('.$id.');"  id="'.$id.'" class="btn btn-inline btn-warning btn-sm ladda-button"><i class="fa fa-edit"></i></button>';
                $sub_array[] = '<button type="button" onClick="eliminar('.$id.');"  id="'.$id.'" class="btn btn-inline btn-danger btn-sm ladda-button"><i class="fa fa-trash"></i></button>';
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
            requiere_rol(array(ROL_ADMIN));
            $usu_id = (int)($_POST["usu_id"] ?? 0);
            if ($usu_id === usuario_actual_id()){
                json_error("No puede eliminar su propia cuenta.");
                break;
            }
            $usuario->delete_usuario($usu_id);
            echo json_encode(array("status" => "ok"));
            break;

        case "mostrar";
            requiere_rol(array(ROL_ADMIN));
            $datos=$usuario->get_usuario_x_id((int)($_POST["usu_id"] ?? 0));
            if(is_array($datos)==true and count($datos)>0){
                $output = array();
                foreach($datos as $row)
                {
                    /* usu_pass NUNCA se devuelve */
                    $output["usu_id"] = $row["usu_id"];
                    $output["usu_nom"] = $row["usu_nom"];
                    $output["usu_ape"] = $row["usu_ape"];
                    $output["usu_usuario"] = $row["usu_usuario"] ?? "";
                    $output["usu_correo"] = $row["usu_correo"];
                    $output["rol_id"] = $row["rol_id"];
                }
                echo json_encode($output);
            }
            break;

        /* ===== Soporte y Administrador: combo de agentes para asignar ===== */
        case "combo";
            requiere_rol(ROLES_SOPORTE);
            $datos = $usuario->get_usuario_x_rol();
            $html = "";
            if(is_array($datos)==true and count($datos)>0){
                $html.= "<option label='Seleccionar'></option>";
                foreach($datos as $row)
                {
                    $html.= "<option value='".(int)$row['usu_id']."'>".e($row['usu_nom'])."</option>";
                }
            }
            echo $html;
            break;

        /* ===== Cambio de contraseña propia (perfil): exige la actual ===== */
        case "password":
            $actual = (string)($_POST["usu_pass_actual"] ?? "");
            $nueva  = (string)($_POST["usu_pass"] ?? "");
            if ($actual === "" || $nueva === ""){
                json_error("Complete la contraseña actual y la nueva.");
                break;
            }
            if ($usuario->cambiar_pass(usuario_actual_id(), $actual, $nueva)){
                echo json_encode(array("status" => "ok"));
            } else {
                json_error("La contraseña actual no es correcta.");
            }
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
?>
