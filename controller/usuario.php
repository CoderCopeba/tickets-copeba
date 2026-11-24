<?php
    require_once("../config/conexion.php");
    require_once("../models/Usuario.php");
    require_once("../models/Area.php");
    $usuario = new Usuario();
    $area = new Area();

    /* Ninguna operación de este controlador es pública */
    requiere_sesion("ajax");

    function json_error($mensaje){
        echo json_encode(array("status" => "error", "message" => $mensaje));
    }

    $op = $_GET["op"] ?? "";

    switch($op){
        /* ===== Solo Administrador del sistema ===== */
        case "guardaryeditar":
            requiere_rol(array(ROL_ADMIN));

            $usu_id      = (string)($_POST["usu_id"] ?? "");
            $usu_usuario = Usuario::normalizar_usuario($_POST["usu_usuario"] ?? "");
            $usu_pass    = (string)($_POST["usu_pass"] ?? "");
            $rol_id      = (int)($_POST["rol_id"] ?? 0);
            $sede_id     = (int)($_POST["sede_id"] ?? 0);
            $es_alta     = ($usu_id === "");

            if (trim((string)($_POST["usu_nom"] ?? "")) === "" || trim((string)($_POST["usu_ape"] ?? "")) === ""){
                json_error("Escribe el nombre y el apellido.");
                break;
            }
            if (!filter_var($_POST["usu_correo"] ?? "", FILTER_VALIDATE_EMAIL)){
                json_error("Escribe un correo válido.");
                break;
            }
            if (!Usuario::usuario_valido($usu_usuario)){
                json_error("Nombre de usuario no válido. Use de 3 a 30 caracteres: letras sin acentos (a-z), números, punto (.), guion (-) o guion bajo (_).");
                break;
            }
            if (!in_array($rol_id, ROLES_VALIDOS, true)){
                json_error("Tipo de cuenta no válido.");
                break;
            }
            if ($sede_id > 0 && !$area->existe("tm_sede", $sede_id)){
                json_error("Sede no válida.");
                break;
            }
            if ($es_alta && $usu_pass === ""){
                json_error("La contraseña es obligatoria para un usuario nuevo.");
                break;
            }
            /* Alta: área de trabajo obligatoria en el mismo paso */
            $alta_area = (int)($_POST["area_id"] ?? 0);
            $alta_fun  = (int)($_POST["fun_id"] ?? 3);
            if ($es_alta){
                if ($sede_id <= 0){
                    json_error("Elige la agencia o sede de la persona.");
                    break;
                }
                if (!$area->existe("tm_area", $alta_area) || !$area->existe("tm_funcion", $alta_fun)){
                    json_error("Elige el área y la función de la persona.");
                    break;
                }
            }
            if (!$es_alta && (int)$usu_id === usuario_actual_id() && $rol_id !== ROL_ADMIN){
                json_error("No puede quitarse a sí mismo la cuenta de Administrador del sistema.");
                break;
            }
            if ($usuario->existe_usuario($usu_usuario, $es_alta ? null : $usu_id)){
                json_error("El nombre de usuario \"".$usu_usuario."\" ya está en uso. Elija otro.");
                break;
            }

            try {
                if($es_alta){
                    $usuario->insert_usuario($_POST["usu_nom"],$_POST["usu_ape"],$usu_usuario,$_POST["usu_correo"],$usu_pass,$rol_id);
                    $usu_id = $usuario->id_por_usuario($usu_usuario);
                }
                else {
                    /* contraseña vacía = no cambiarla */
                    $usuario->update_usuario((int)$usu_id,$_POST["usu_nom"],$_POST["usu_ape"],$usu_usuario,$_POST["usu_correo"],$usu_pass,$rol_id);
                }
                $usuario->set_sede((int)$usu_id, $sede_id);
                if ($es_alta){
                    $puesto = trim(mb_substr((string)($_POST["au_puesto"] ?? ""), 0, 80)) ?: null;
                    $area->guardar_membresia((int)$usu_id, $alta_area, $sede_id, $alta_fun, $puesto);
                }
                /* La contraseña que pone el administrador es temporal: la persona elegirá la suya al entrar */
                if ($usu_pass !== "" && (int)$usu_id !== usuario_actual_id()){
                    $usuario->marcar_temporal((int)$usu_id, $usu_pass, 72);
                    $usuario->registrar_evento((int)$usu_id, usuario_actual_id(), $es_alta ? "cuenta_creada" : "temporal_generada", "Asignada por el administrador · 72 h");
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
            echo json_encode(array("status" => "ok", "usu_id" => (int)$usu_id));
            break;

        /* Lista para la tabla: texto plano; la vista lo pinta con .text() */
        case "listar":
            requiere_rol(array(ROL_ADMIN));
            $data = array();
            foreach($usuario->listar_con_areas() as $row){
                $data[] = array(
                    "id"      => (int)$row["usu_id"],
                    "nombre"  => trim($row["usu_nom"]." ".$row["usu_ape"]),
                    "usuario" => $row["usu_usuario"] ?? "",
                    "correo"  => $row["usu_correo"],
                    "sede"    => $row["sede_nom"] ?? "",
                    "areas"   => $row["areas"] ? explode("|", $row["areas"]) : array(),
                    "admin"   => (int)$row["rol_id"] === ROL_ADMIN,
                );
            }
            echo json_encode(array("data" => $data));
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

        case "mostrar":
            requiere_rol(array(ROL_ADMIN));
            $datos = $usuario->get_usuario_x_id((int)($_POST["usu_id"] ?? 0));
            if (is_array($datos) && count($datos) > 0){
                $row = $datos[0];
                /* usu_pass NUNCA se devuelve */
                echo json_encode(array(
                    "usu_id"      => (int)$row["usu_id"],
                    "usu_nom"     => $row["usu_nom"],
                    "usu_ape"     => $row["usu_ape"],
                    "usu_usuario" => $row["usu_usuario"] ?? "",
                    "usu_correo"  => $row["usu_correo"],
                    "rol_id"      => (int)$row["rol_id"],
                    "sede_id"     => (int)($row["sede_id"] ?? 0),
                ));
            }
            break;

        /* ===== Quien atiende tickets: lista de agentes para asignar ===== */
        case "combo":
            requiere_soporte();
            $html = "<option value=''>Seleccionar</option>";
            foreach ($area->agentes() as $row){
                $html .= "<option value='".(int)$row["usu_id"]."'>".e(trim($row["usu_nom"]." ".$row["usu_ape"]))."</option>";
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
            if (mb_strlen($nueva) < Usuario::PASS_MINIMO){
                json_error("La nueva contraseña debe tener al menos ".Usuario::PASS_MINIMO." caracteres.");
                break;
            }
            if ($usuario->cambiar_pass(usuario_actual_id(), $actual, $nueva)){
                echo json_encode(array("status" => "ok"));
            } else {
                json_error("La contraseña actual no es correcta.");
            }
            break;

        /* ===== Control de cuentas (Informática): estado de contraseñas, nunca las contraseñas ===== */
        case "control_listar":
            requiere_soporte();
            $data = array();
            foreach ($usuario->control_lista() as $r){
                $ultimo = $r["ultimo"] ? explode("|", $r["ultimo"], 3) : null;
                $data[] = array(
                    "id"        => (int)$r["usu_id"],
                    "nombre"    => trim($r["usu_nom"]." ".$r["usu_ape"]),
                    "usuario"   => $r["usu_usuario"],
                    "sede"      => $r["sede_nom"] ?? "",
                    "areas"     => $r["areas"] ?? "",
                    "admin"     => (int)$r["rol_id"] === ROL_ADMIN,
                    "temporal"  => (int)$r["usu_pass_temporal"] === 1,
                    "vencida"   => (int)$r["vencida"] === 1,
                    "expira"    => $r["usu_pass_expira"],
                    "fech_pass" => $r["fech_pass"],
                    "ultimo"    => $ultimo ? array("evento" => $ultimo[0], "fecha" => $ultimo[1], "actor" => trim($ultimo[2])) : null,
                );
            }
            echo json_encode(array("data" => $data, "ahora" => date("Y-m-d H:i:s")));
            break;

        case "generar_temporal":
            requiere_soporte();
            $destino = (int)($_POST["usu_id"] ?? 0);
            $cuenta  = $usuario->cuenta_activa($destino);
            if ($cuenta === null){
                json_error("La cuenta no existe o está desactivada.");
                break;
            }
            if ($destino === usuario_actual_id()){
                json_error("Para tu propia cuenta usa Perfil → Cambiar contraseña.");
                break;
            }
            if ($cuenta["rol_id"] === ROL_ADMIN && !es_admin()){
                json_error("Solo un Administrador del sistema puede restablecer la cuenta de otro administrador.");
                break;
            }
            $temporal = $usuario->generar_temporal($destino, usuario_actual_id());
            /* Se devuelve una sola vez para dictarla; no queda guardada en claro */
            echo json_encode(array("status" => "ok", "temporal" => $temporal, "horas" => Usuario::HORAS_TEMPORAL,
                                   "nombre" => trim($cuenta["usu_nom"]." ".$cuenta["usu_ape"])));
            break;

        case "historial":
            requiere_soporte();
            $eventos = array();
            foreach ($usuario->historial((int)($_POST["usu_id"] ?? 0)) as $h){
                $eventos[] = array("evento" => $h["ue_evento"], "detalle" => $h["ue_detalle"], "fecha" => $h["fech_crea"], "actor" => $h["actor"]);
            }
            echo json_encode($eventos);
            break;

        default:
            auth_denegar(404, "ajax", "Operación no válida.");
    }
?>
