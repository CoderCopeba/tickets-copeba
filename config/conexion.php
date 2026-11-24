<?php
    /* Sesión: cookie solo HTTP (no accesible desde JS), SameSite=Lax y Secure si hay HTTPS */
    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || (($_SERVER["SERVER_PORT"] ?? null) == 443);
        ini_set("session.use_strict_mode", "1");
        ini_set("session.use_only_cookies", "1");
        session_set_cookie_params(array(
            "lifetime" => 0,
            "path"     => "/",
            "secure"   => $https,
            "httponly" => true,
            "samesite" => "Lax",
        ));
        session_start();
    }
    require_once(__DIR__ . "/env.php");
    require_once(__DIR__ . "/auth.php");
    require_once(__DIR__ . "/html.php");
    require_once(__DIR__ . "/seguridad.php");

    class Conectar{
        protected $dbh;
        /* Una sola conexión por petición, compartida por todos los modelos */
        private static $pdo = null;

        protected function Conexion(){
            if (self::$pdo !== null) {
                return $this->dbh = self::$pdo;
            }
            try {
				$dbHost = env_get("DB_HOST");
				$dbPort = env_get("DB_PORT");
				$dbName = env_get("DB_NAME");
				$dbUser = env_get("DB_USER");
				$dbPassword = env_get("DB_PASSWORD");

				$opciones = array(
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_EMULATE_PREPARES => false,   /* consultas preparadas reales en MySQL */
				);
				/* Base en la nube (p. ej. TiDB Cloud): conexión cifrada TLS verificando el certificado.
				   DB_SSL=1 y DB_SSL_CA = ruta del archivo de certificados raíz (cacert.pem). */
				if (env_get("DB_SSL", false) === "1") {
					$ca = env_get("DB_SSL_CA", false);
					if ($ca) {
						$opciones[PDO::MYSQL_ATTR_SSL_CA] = $ca;
					}
					$opciones[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
					/* Reutilizar la conexión cifrada entre peticiones: evita negociar TLS
					   con el servidor remoto en cada clic (la mayor parte de la demora) */
					$opciones[PDO::ATTR_PERSISTENT] = true;
				}

				$conectar = $this->dbh = new PDO(
					"mysql:host=" . $dbHost . ";port=" . $dbPort . ";dbname=" . $dbName . ";charset=utf8mb4",
					$dbUser,
					$dbPassword,
					$opciones
				);
				self::$pdo = $conectar;
				return $conectar;
			} catch (Exception $e) {
				error_log("Error de conexión a la base de datos: ".$e->getMessage());
				self::error_conexion($e);
			}
        }

        public function set_names(){
			/* el juego de caracteres ya se fija al conectar (charset=utf8mb4) */
			return true;
        }

        /* Sin base de datos: en vez de una página en blanco, un aviso claro.
           El detalle técnico solo se muestra en la propia computadora (localhost),
           nunca a usuarios de la red. Nunca incluye contraseñas. */
        private static function error_conexion(Exception $e){
            $local = in_array($_SERVER["REMOTE_ADDR"] ?? "", array("127.0.0.1", "::1"), true) || PHP_SAPI === "cli";
            $msg = $e->getMessage();
            $pista = "Revisa los datos DB_* del archivo .env.";
            if (stripos($msg, "Access denied") !== false)          { $pista = "Usuario o contraseña de la base incorrectos (DB_USER / DB_PASSWORD en .env)."; }
            elseif (stripos($msg, "Unknown database") !== false)   { $pista = "No existe la base indicada en DB_NAME."; }
            elseif (stripos($msg, "SSL") !== false || stripos($msg, "certificate") !== false) { $pista = "Problema con la conexión cifrada: revisa DB_SSL=1 y que DB_SSL_CA apunte a un archivo que exista (p. ej. C:\\laragon\\etc\\ssl\\cacert.pem)."; }
            elseif (stripos($msg, "timed out") !== false || stripos($msg, "getaddrinfo") !== false || stripos($msg, "refused") !== false || stripos($msg, "2002") !== false) {
                $pista = "No se alcanza el servidor: revisa internet, DB_HOST / DB_PORT y que tu IP esté permitida en TiDB Cloud (Networking).";
            }
            elseif (stripos($msg, "could not find driver") !== false) { $pista = "Falta la extensión pdo_mysql de PHP."; }
            $detalle = $local ? preg_replace('/(password|pwd)=\S+/i', '$1=***', $msg) : "";

            if (PHP_SAPI === "cli") { fwrite(STDERR, "Sin conexión a la base: ".$pista."\n".$detalle."\n"); exit(1); }
            http_response_code(503);
            $ajax = stripos($_SERVER["SCRIPT_NAME"] ?? "", "/controller/") !== false;
            if ($ajax) {
                header("Content-Type: application/json; charset=utf-8");
                echo json_encode(array("status" => "error", "code" => 503, "message" => "Sin conexión a la base de datos. ".$pista));
                exit();
            }
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Sin conexión</title></head>'
               . '<body style="font-family:Segoe UI,Arial,sans-serif;padding:40px;max-width:720px">'
               . '<h2 style="color:#b42318">No se pudo conectar a la base de datos</h2>'
               . '<p>'.htmlspecialchars($pista, ENT_QUOTES, "UTF-8").'</p>'
               . ($detalle !== "" ? '<pre style="background:#f6f3ec;padding:12px;white-space:pre-wrap">'.htmlspecialchars($detalle, ENT_QUOTES, "UTF-8").'</pre>' : '')
               . '<p>Diagnóstico completo: <code>php instalar/diagnostico.php</code></p></body></html>';
            exit();
        }

        /* Dirección base del sistema, p. ej. "http://localhost/tickets-copeba/".
           1) APP_URL del .env si está definida (útil en un servidor);
           2) si no, se calcula sola a partir de la carpeta del proyecto y del
              servidor: funciona aunque la carpeta tenga otro nombre o se entre
              por otra dirección (tickets-copeba.test, una IP de la red, etc.). */
        public static function ruta(){
            $app_url = env_get("APP_URL", false);
            if ($app_url) {
                return rtrim($app_url, "/")."/";
            }
            $https  = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || (($_SERVER["SERVER_PORT"] ?? null) == 443);
            $host   = $_SERVER["HTTP_HOST"] ?? "localhost";
            /* Se compara el archivo que se está ejecutando con la carpeta del proyecto:
               lo que sobra al final de la URL es la parte interna; lo de antes, la base.
               Funciona con otro nombre de carpeta, accesos directos y alias. */
            $raiz    = str_replace("\\", "/", (string)realpath(__DIR__."/.."));
            $archivo = str_replace("\\", "/", (string)realpath($_SERVER["SCRIPT_FILENAME"] ?? ""));
            $script  = str_replace("\\", "/", (string)($_SERVER["SCRIPT_NAME"] ?? ""));
            $base    = "/tickets-copeba";
            if ($archivo !== "" && stripos($archivo, $raiz."/") === 0) {
                $interna = substr($archivo, strlen($raiz));            /* p. ej. "/view/Home/index.php" */
                if (strlen($script) >= strlen($interna) && strcasecmp(substr($script, -strlen($interna)), $interna) === 0) {
                    $base = substr($script, 0, strlen($script) - strlen($interna));
                }
            }
            return ($https ? "https" : "http")."://".$host.rtrim($base, "/")."/";
		}

    }
?>