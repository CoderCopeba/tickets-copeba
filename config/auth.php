<?php
/*
 * Autenticación y autorización centralizadas.
 * Se carga desde config/conexion.php, así que está disponible en todo el proyecto.
 *
 * Roles:
 *   1 = Usuario        -> crea tickets y solo ve/responde los suyos
 *   2 = Soporte        -> gestiona, asigna y cierra todos los tickets
 *   3 = Administrador  -> todo lo de Soporte + mantenimiento de usuarios
 *
 * Modo 'ajax'  -> responde 401/403/404 en JSON y termina.
 * Modo 'vista' -> sin sesión redirige al login; sin permiso muestra 403.
 */

const ROL_USUARIO = 1;
const ROL_SOPORTE = 2;
const ROL_ADMIN   = 3;
const ROLES_VALIDOS = array(ROL_USUARIO, ROL_SOPORTE, ROL_ADMIN);
const ROLES_SOPORTE = array(ROL_SOPORTE, ROL_ADMIN);

function usuario_actual_id(){
    return isset($_SESSION["usu_id"]) ? (int)$_SESSION["usu_id"] : null;
}

function rol_actual(){
    return isset($_SESSION["rol_id"]) ? (int)$_SESSION["rol_id"] : null;
}

function es_soporte(){
    return in_array(rol_actual(), ROLES_SOPORTE, true);
}

function es_admin(){
    return rol_actual() === ROL_ADMIN;
}

function rol_nombre($rol_id){
    switch ((int)$rol_id){
        case ROL_USUARIO: return "Usuario";
        case ROL_SOPORTE: return "Soporte";
        case ROL_ADMIN:   return "Administrador";
        default:          return "Desconocido";
    }
}

/* Imagen de avatar por rol (solo existen public/1.jpg y public/2.jpg) */
function rol_imagen($rol_id){
    return ((int)$rol_id === ROL_USUARIO) ? "1.jpg" : "2.jpg";
}

/* Escapar texto para HTML */
function e($texto){
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
}

function auth_denegar($codigo, $modo, $mensaje = null){
    if ($modo === "vista"){
        if ($codigo === 401){
            header("Location:".Conectar::ruta()."index.php");
            exit();
        }
        http_response_code($codigo);
        $titulo = ($codigo === 404) ? "No encontrado" : "Acceso denegado";
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>'.e($titulo).'</title></head>'
           . '<body style="font-family:sans-serif;padding:40px;">'
           . '<h2>'.e($codigo).' – '.e($titulo).'</h2>'
           . '<p>'.e($mensaje ?? "No tiene permiso para ver esta página.").'</p>'
           . '<p><a href="'.e(Conectar::ruta()).'view/Home/">Volver al inicio</a></p>'
           . '</body></html>';
        exit();
    }
    http_response_code($codigo);
    header("Content-Type: application/json; charset=utf-8");
    if ($mensaje === null){
        $mensaje = ($codigo === 401) ? "Sesión no iniciada o expirada." : (($codigo === 404) ? "No encontrado." : "No tiene permiso para esta operación.");
    }
    echo json_encode(array("status" => "error", "code" => $codigo, "message" => $mensaje));
    exit();
}

function requiere_sesion($modo = "ajax"){
    if (usuario_actual_id() === null || rol_actual() === null){
        auth_denegar(401, $modo);
    }
}

function requiere_rol(array $roles, $modo = "ajax"){
    requiere_sesion($modo);
    if (!in_array(rol_actual(), $roles, true)){
        auth_denegar(403, $modo);
    }
}

/*
 * Comprueba que el usuario actual puede acceder al ticket:
 * Soporte/Admin -> cualquiera que exista; Usuario -> solo los suyos.
 * $ticket es una instancia del modelo Ticket.
 */
function requiere_acceso_ticket($ticket, $tick_id, $modo = "ajax"){
    requiere_sesion($modo);
    $tick_id = (int)$tick_id;
    $propietario = ($tick_id > 0) ? $ticket->get_ticket_propietario($tick_id) : null;
    if ($propietario === null){
        auth_denegar(404, $modo, "El ticket no existe.");
    }
    if (!es_soporte() && $propietario !== usuario_actual_id()){
        auth_denegar(403, $modo, "No tiene permiso para ver este ticket.");
    }
    return $tick_id;
}
