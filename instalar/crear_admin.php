<?php
/* =====================================================================
   Crea la primera cuenta Administrador (rol 3) en una instalación nueva.
   Uso (desde la carpeta del proyecto, en una terminal):
       php instalar/crear_admin.php
   Pide los datos y la contraseña de forma interactiva: así ninguna
   contraseña queda escrita en el repositorio ni en el historial.
   Solo funciona por línea de comandos; desde el navegador responde 403.
   ===================================================================== */
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit;
}

chdir(__DIR__."/../controller");   /* mismas rutas relativas que la aplicación */
require_once(__DIR__."/../config/conexion.php");
require_once(__DIR__."/../models/Usuario.php");

function preguntar($texto){
    echo $texto;
    return trim((string) fgets(STDIN));
}

/* Lee la contraseña sin mostrarla cuando el sistema lo permite */
function preguntar_oculto($texto){
    if (DIRECTORY_SEPARATOR === "\\") {
        $ps = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; '
            .'[Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
        echo $texto;
        $valor = shell_exec($ps);
        if ($valor !== null && $valor !== false) {
            return rtrim($valor, "\r\n");
        }
        return preguntar("");
    }
    echo $texto;
    shell_exec("stty -echo");
    $valor = trim((string) fgets(STDIN));
    shell_exec("stty echo");
    echo "\n";
    return $valor;
}

echo "=== Copeba Tickets: crear cuenta Administrador ===\n\n";

$usuario = new Usuario();

$usu_usuario = Usuario::normalizar_usuario(preguntar("Usuario (3-30: letras minúsculas, números, . _ -): "));
if (!Usuario::usuario_valido($usu_usuario)) {
    exit("Usuario no válido.\n");
}
if ($usuario->existe_usuario($usu_usuario)) {
    exit("Ese usuario ya existe. Inicia sesión con él o elige otro nombre.\n");
}

$nombre   = preguntar("Nombre: ");
$apellido = preguntar("Apellido: ");
$correo   = preguntar("Correo: ");
if ($nombre === "" || $apellido === "" || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit("Nombre, apellido y un correo válido son obligatorios.\n");
}

$pass  = preguntar_oculto("Contraseña (mínimo 8 caracteres): ");
$pass2 = preguntar_oculto("Repite la contraseña: ");
if (strlen($pass) < 8) {
    exit("La contraseña debe tener al menos 8 caracteres.\n");
}
if (!hash_equals($pass, $pass2)) {
    exit("Las contraseñas no coinciden.\n");
}

$usuario->insert_usuario($nombre, $apellido, $usu_usuario, $correo, $pass, ROL_ADMIN);

if ($usuario->existe_usuario($usu_usuario)) {
    /* El administrador del sistema pertenece a Informática, en la Sede Central */
    require_once(__DIR__."/../models/Area.php");
    $area    = new Area();
    $usu_id  = $usuario->id_por_usuario($usu_usuario);
    $area_id = $area->id_area("Informática");
    $sede_id = $area->id_sede_administrativa();
    if ($area_id && $sede_id) {
        $usuario->set_sede($usu_id, $sede_id);
        $area->guardar_membresia($usu_id, $area_id, $sede_id, 3, "Administrador de sistemas");
    }
    echo "\nListo. Ya puedes iniciar sesión con el usuario \"".$usu_usuario."\".\n";
    echo "Las demás cuentas y sus áreas se crean desde la pantalla Usuarios.\n";
} else {
    exit("No se pudo crear la cuenta. Revisa la conexión en .env.\n");
}
