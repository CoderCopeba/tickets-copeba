<?php
/*
 * Autenticación y autorización centralizadas.
 * Se carga desde config/conexion.php, así que está disponible en todo el proyecto.
 *
 * Tipo de cuenta (tm_rol):
 *   1 = Colaborador               -> crea tickets y ve/responde los suyos
 *   3 = Administrador del sistema -> todo, incluido mantenimiento de usuarios y áreas
 *
 * El trabajo de cada persona lo define su ÁREA (td_area_usuario), como en Canvas:
 * quien pertenece a un área que atiende tickets (Informática) gestiona tickets.
 *
 * Modo 'ajax'  -> responde 401/403/404 en JSON y termina.
 * Modo 'vista' -> sin sesión redirige al login; sin permiso muestra 403.
 */

const ROL_COLABORADOR = 1;
const ROL_ADMIN       = 3;
const ROLES_VALIDOS   = array(ROL_COLABORADOR, ROL_ADMIN);

function usuario_actual_id(){
    return isset($_SESSION["usu_id"]) ? (int)$_SESSION["usu_id"] : null;
}

function rol_actual(){
    return isset($_SESSION["rol_id"]) ? (int)$_SESSION["rol_id"] : null;
}

function es_admin(){
    return rol_actual() === ROL_ADMIN;
}

/* Gestiona tickets: administrador del sistema o miembro de un área que atiende tickets.
   Se consulta en cada petición (una vez), así un cambio de área aplica de inmediato. */
function es_soporte(){
    if (usuario_actual_id() === null){
        return false;
    }
    $c = cuenta_sesion();
    return es_admin() || ($c !== null && $c["atiende"]);
}

/* Reportes de actividad: administradores del sistema y la Responsable de
   Gerencia y Administración (gerente general). Solo lectura. */
const AREA_GERENCIA = "Gerencia y Administración";
function puede_ver_reportes(){
    if (usuario_actual_id() === null){ return false; }
    $c = cuenta_sesion();
    return es_admin() || ($c !== null && $c["responsable_reportes"]);
}

/* Cuenta de la sesión leída de la base UNA vez por petición (tipo, si está activa,
   contraseña temporal, si atiende tickets y si ve reportes): una sola consulta.
   Se relee en cada petición, así un cambio de área o de tipo aplica de inmediato. */
function cuenta_sesion(){
    static $cargada = false, $cuenta = null;
    if (!$cargada){
        $cargada = true;
        if (usuario_actual_id() !== null){
            require_once(__DIR__."/../models/Usuario.php");
            $cuenta = (new Usuario())->cuenta_activa(usuario_actual_id(), AREA_GERENCIA);
        }
    }
    return $cuenta;
}

function rol_nombre($rol_id){
    switch ((int)$rol_id){
        case ROL_COLABORADOR: return "Colaborador";
        case ROL_ADMIN:       return "Administrador del sistema";
        default:              return "Desconocido";
    }
}

/* Imagen de avatar por rol (solo existen public/1.jpg y public/2.jpg) */
function rol_imagen($rol_id){
    return ((int)$rol_id === ROL_COLABORADOR) ? "1.jpg" : "2.jpg";
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

/* Relee de la base (una vez por petición) el tipo de cuenta y si sigue activa.
   Así un cambio hecho por el administrador aplica sin cerrar sesión, y una
   cuenta desactivada pierde el acceso de inmediato. */
function sesion_actualizar($modo){
    static $hecho = false;
    if ($hecho) { return; }
    $hecho = true;
    $cuenta = cuenta_sesion();
    if ($cuenta === null){
        $_SESSION = array();
        auth_denegar(401, $modo);
    }
    $_SESSION["rol_id"]  = in_array($cuenta["rol_id"], ROLES_VALIDOS, true) ? $cuenta["rol_id"] : ROL_COLABORADOR;
    $_SESSION["usu_nom"] = $cuenta["usu_nom"];
    $_SESSION["usu_ape"] = $cuenta["usu_ape"];

    /* Con contraseña temporal solo se permite la pantalla "Crea tu contraseña"
       (que define PERMITE_PASS_TEMPORAL antes de pedir la sesión).
       En "Ver como" no aplica: quien mira es el administrador. */
    if ((int)$cuenta["usu_pass_temporal"] === 1 && !defined("PERMITE_PASS_TEMPORAL") && !viendo_como()){
        if ($modo === "vista"){
            header("Location:".Conectar::ruta()."view/CambiarPass/");
            exit();
        }
        auth_denegar(403, $modo, "Primero debes crear tu contraseña.");
    }
}

/* ¿La cuenta en sesión tiene todavía una contraseña temporal? */
function pass_temporal_pendiente(){
    $c = cuenta_sesion();
    return $c !== null && (int)$c["usu_pass_temporal"] === 1;
}

function requiere_sesion($modo = "ajax"){
    if (usuario_actual_id() === null || rol_actual() === null){
        auth_denegar(401, $modo);
    }
    sesion_actualizar($modo);
    /* Toda petición AJAX por POST debe traer el token CSRF de la sesión */
    if ($modo === "ajax" && es_post() && !csrf_valido()){
        auth_denegar(403, $modo, "La sesión caducó o la petición no es válida. Recarga la página.");
    }
    /* "Ver como": solo lectura */
    if ($modo === "ajax" && es_post() && viendo_como() && !operacion_de_lectura()){
        auth_denegar(403, $modo, "Estás en modo \"Ver como\" (solo lectura). Vuelve a tu cuenta para hacer cambios.");
    }
    /* Las consultas de solo lectura liberan la sesión en cuanto se validan: PHP bloquea
       el archivo de sesión mientras dura la petición, y sin esto las peticiones de una
       misma pantalla esperan en fila una detrás de otra. $_SESSION se sigue pudiendo leer.
       cuenta.php queda fuera porque "volver" modifica la sesión. */
    if ($modo === "ajax" && operacion_de_lectura() && basename($_SERVER["SCRIPT_NAME"] ?? "") !== "cuenta.php"){
        session_write_close();
    }
}

/* ===== "Ver como" (solo Administrador del sistema, solo lectura) =====
   La sesión toma la identidad de la persona para mostrar exactamente lo que ve,
   y guarda quién es el administrador real para poder volver. */
function viendo_como(){
    return !empty($_SESSION["ver_como_admin"]);
}

function admin_real_id(){
    return viendo_como() ? (int)$_SESSION["ver_como_admin"] : usuario_actual_id();
}

/* Operaciones que solo leen datos: las únicas permitidas mientras se "ve como" */
function operacion_de_lectura(){
    $permitidas = array(
        "ticket.php"  => array("lista", "detalle"),
        "inicio.php"  => array("resumen"),
        "reporte.php" => array("resumen"),
        "usuario.php" => array("listar", "mostrar", "combo", "control_listar", "historial"),
        "area.php"    => array("mis_areas", "catalogos", "miembros", "membresias"),
        "cuenta.php"  => array("volver"),
        "tarea.php"   => array("lista", "personas", "detalle"),
        "plan.php"    => array("periodo", "equipo", "ver_plan", "tickets"),
    );
    $archivo = basename($_SERVER["SCRIPT_NAME"] ?? "");
    $op = (string)($_GET["op"] ?? "");
    return isset($permitidas[$archivo]) && in_array($op, $permitidas[$archivo], true);
}

function requiere_rol(array $roles, $modo = "ajax"){
    requiere_sesion($modo);
    if (!in_array(rol_actual(), $roles, true)){
        auth_denegar(403, $modo);
    }
}

/* Atender tickets: administrador o miembro de un área que atiende */
function requiere_soporte($modo = "ajax"){
    requiere_sesion($modo);
    if (!es_soporte()){
        auth_denegar(403, $modo);
    }
}

/*
 * Comprueba que el usuario actual puede acceder al ticket:
 * Quien atiende tickets -> cualquiera que exista; el resto -> solo los suyos.
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
