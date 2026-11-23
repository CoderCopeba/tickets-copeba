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

				$conectar = $this->dbh = new PDO(
					"mysql:host=" . $dbHost . ";port=" . $dbPort . ";dbname=" . $dbName . ";charset=utf8mb4",
					$dbUser,
					$dbPassword,
					array(
						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
						PDO::ATTR_EMULATE_PREPARES => false,   /* consultas preparadas reales en MySQL */
					)
				);
				self::$pdo = $conectar;
				return $conectar;
			} catch (Exception $e) {
				error_log("Error de conexión a la base de datos");
				die();
			}
        }

        public function set_names(){
			/* el juego de caracteres ya se fija al conectar (charset=utf8mb4) */
			return true;
        }

        public static function ruta(){
            //Local
            return "http://localhost/tickets-copeba/";
		}

    }
?>