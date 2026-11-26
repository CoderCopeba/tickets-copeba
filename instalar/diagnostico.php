<?php
/* =====================================================================
   Diagnóstico de la instalación (solo terminal; nunca muestra contraseñas)
   Uso, desde la carpeta del proyecto:
       php instalar/diagnostico.php
   Revisa: PHP y extensiones, Composer, .env, certificado TLS, conexión a la
   base (local o nube), tablas, permisos de la cuenta y dirección del sistema.
   ===================================================================== */
if (PHP_SAPI !== "cli") { http_response_code(403); exit; }

$raiz = realpath(__DIR__."/..");
$fallos = 0;
function ok($t)    { echo "  [OK]    ".$t."\n"; }
function aviso($t) { echo "  [AVISO] ".$t."\n"; }
function falla($t) { global $fallos; $fallos++; echo "  [FALLA] ".$t."\n"; }

echo "\n=== Copeba Tickets: diagnóstico ===\n\n1. PHP\n";
version_compare(PHP_VERSION, "8.3", ">=") ? ok("PHP ".PHP_VERSION) : falla("PHP ".PHP_VERSION." (se requiere 8.3 o superior)");
foreach (array("pdo_mysql", "openssl", "mbstring", "gd") as $ext) {
    extension_loaded($ext) ? ok("Extensión ".$ext) : falla("Falta la extensión ".$ext." (actívala en Laragon: PHP > Extensions)");
}

echo "\n2. Archivos\n";
is_file($raiz."/vendor/autoload.php") ? ok("vendor/ (Composer)") : falla("Falta vendor/: ejecuta  composer install");
if (!is_file($raiz."/.env")) {
    falla("Falta .env: copia .env.example a .env y complétalo (ver AGENTS.md)");
    exit(1);
}
ok(".env existe");

require_once($raiz."/config/env.php");
$req = array("DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASSWORD");
foreach ($req as $k) {
    env_get($k, false) ? ok($k.($k === "DB_PASSWORD" ? " definido (oculto)" : " = ".env_get($k, false))) : falla($k." está vacío en .env");
}
$ssl = env_get("DB_SSL", false) === "1";
$es_nube = stripos((string)env_get("DB_HOST", false), "tidbcloud.com") !== false;
if ($es_nube && !$ssl) { falla("La base está en TiDB Cloud pero DB_SSL no es 1 (la nube exige conexión cifrada)"); }
if ($ssl) {
    $ca = env_get("DB_SSL_CA", false);
    if (!$ca)               { falla("DB_SSL=1 pero DB_SSL_CA está vacío (usa C:\\laragon\\etc\\ssl\\cacert.pem)"); }
    elseif (!is_file($ca))  { falla("DB_SSL_CA apunta a un archivo que NO existe: ".$ca); }
    else                    { ok("Certificados raíz: ".$ca); }
}

echo "\n3. Conexión a la base ".($es_nube ? "(nube TiDB Cloud)" : "(local)")."\n";
$opciones = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10);
if ($ssl && env_get("DB_SSL_CA", false)) {
    $opciones[PDO::MYSQL_ATTR_SSL_CA] = env_get("DB_SSL_CA", false);
    $opciones[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}
try {
    $t0 = microtime(true);
    $pdo = new PDO("mysql:host=".env_get("DB_HOST", false).";port=".env_get("DB_PORT", false).";dbname=".env_get("DB_NAME", false).";charset=utf8mb4",
                   env_get("DB_USER", false), env_get("DB_PASSWORD", false), $opciones);
    ok("Conectado en ".round((microtime(true) - $t0) * 1000)." ms · servidor ".$pdo->query("SELECT VERSION()")->fetchColumn());
    $cipher = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM);
    if ($ssl) { !empty($cipher[1]) ? ok("Cifrado TLS: ".$cipher[1]) : falla("La conexión NO está cifrada"); }
} catch (Exception $e) {
    $m = $e->getMessage();
    falla("No conecta: ".preg_replace('/(password|pwd)=\S+/i', '$1=***', $m));
    if (stripos($m, "Access denied") !== false) { echo "          -> Revisa DB_USER y DB_PASSWORD (pídelos a Informática en persona).\n"; }
    elseif (stripos($m, "timed out") !== false || stripos($m, "2002") !== false) {
        echo "          -> Sin respuesta del servidor: revisa internet y, en TiDB Cloud > Networking, que tu IP pública esté permitida.\n";
    }
    exit(1);
}

echo "\n4. Tablas y datos\n";
$tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach (array("tm_usuario", "tm_ticket", "tm_area", "tm_sede", "tm_prioridad", "td_area_usuario", "td_usuario_evento", "tm_tarea", "td_tarea_usuario") as $t) {
    in_array($t, $tablas, true) ? ok("Tabla ".$t) : falla("Falta la tabla ".$t." (la base no está actualizada: aplica las migraciones de instalar/migraciones)");
}
if (in_array("tm_ticket", $tablas, true)) {
    $cols = $pdo->query("SHOW COLUMNS FROM tm_ticket")->fetchAll(PDO::FETCH_COLUMN);
    in_array("fech_cierre", $cols, true) ? ok("Migraciones al día (tm_ticket.fech_cierre existe)") : falla("Faltan migraciones recientes (tm_ticket.fech_cierre no existe)");
    ok($pdo->query("SELECT COUNT(*) FROM tm_usuario WHERE est = 1")->fetchColumn()." cuentas activas · "
       .$pdo->query("SELECT COUNT(*) FROM tm_ticket")->fetchColumn()." tickets");
    (int)$pdo->query("SELECT COUNT(*) FROM tm_usuario WHERE est = 1 AND rol_id = 3")->fetchColumn() > 0
        ? ok("Hay al menos un Administrador del sistema") : falla("No hay ningún Administrador: ejecuta  php instalar/crear_admin.php");
}

echo "\n5. Permisos de la cuenta de la aplicación\n";
$grants = implode(" ", $pdo->query("SHOW GRANTS")->fetchAll(PDO::FETCH_COLUMN));
if (preg_match('/ALL PRIVILEGES|SUPER|\bDROP\b|\bDELETE\b/i', $grants) && stripos((string)env_get("DB_USER", false), "root") !== false) {
    aviso("La aplicación usa una cuenta con permisos de administrador (".env_get("DB_USER", false)."). Usa la cuenta tickets_app.");
} elseif (preg_match('/\bDELETE\b|\bDROP\b/i', $grants)) {
    aviso("La cuenta tiene permisos de más (DELETE/DROP). Lo recomendado: solo SELECT, INSERT, UPDATE.");
} else {
    ok("Permisos mínimos (SELECT, INSERT, UPDATE)");
}

echo "\n6. Dirección del sistema\n";
$carpeta = basename($raiz);
$docroot = "C:/laragon/www";
if (stripos(str_replace("\\", "/", $raiz), $docroot) === 0) {
    ok("Abre en el navegador:  http://localhost/".str_replace("\\", "/", substr(str_replace("\\", "/", $raiz), strlen($docroot) + 1))."/");
} else {
    aviso("El proyecto no está dentro de C:\\laragon\\www (está en ".$raiz."). Muévelo ahí o define APP_URL en .env.");
}
if ($carpeta !== "tickets-copeba") {
    aviso("La carpeta se llama \"".$carpeta."\" (no \"tickets-copeba\"). Funciona igual: las rutas se calculan solas.");
}
if (env_get("APP_URL", false)) { ok("APP_URL fijada: ".env_get("APP_URL", false)); }

echo "\n".($fallos ? "Hay ".$fallos." problema(s) que corregir.\n" : "Todo en orden.\n");
exit($fallos ? 1 : 0);
