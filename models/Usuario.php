<?php
    class Usuario extends Conectar{

        /* ===== Nombre de usuario: reglas =====
           a-z, 0-9, punto, guion y guion bajo; 3 a 30 caracteres; se guarda en minúsculas. */
        public static function normalizar_usuario($usu_usuario){
            return strtolower(trim((string)$usu_usuario));
        }

        public static function usuario_valido($usu_usuario){
            return preg_match('/^[a-z0-9._-]{3,30}$/', (string)$usu_usuario) === 1;
        }

        /* ===== Contraseñas =====
           Formato moderno: password_hash()/password_verify().
           Formato heredado: MD5 (exactamente 32 caracteres hex). Se acepta una vez y se migra.
           Cualquier otro formato se rechaza (nunca se compara como texto plano). */
        public static function hash_pass($pass){
            return password_hash((string)$pass, PASSWORD_DEFAULT);
        }

        /* Devuelve 'moderno', 'md5' o false */
        private static function comprobar_pass($pass, $hash){
            $pass = (string)$pass;
            $hash = (string)$hash;
            if ($hash === ""){
                return false;
            }
            if (password_verify($pass, $hash)){
                return "moderno";
            }
            if (preg_match('/^[a-f0-9]{32}$/i', $hash) === 1 && hash_equals(strtolower($hash), md5($pass))){
                return "md5";
            }
            return false;
        }

        /* Verifica la contraseña de un usuario activo y, si procede, migra/rehace el hash */
        private function verificar_pass_usuario($usu_id, $pass, $hash){
            $resultado = self::comprobar_pass($pass, $hash);
            if ($resultado === false){
                return false;
            }
            if ($resultado === "md5" || password_needs_rehash($hash, PASSWORD_DEFAULT)){
                try {
                    $this->guardar_hash($usu_id, self::hash_pass($pass));
                } catch (Exception $e) {
                    /* el login sigue siendo válido aunque no se pueda migrar ahora */
                    error_log("No se pudo actualizar el hash del usuario ".(int)$usu_id);
                }
            }
            return true;
        }

        private function guardar_hash($usu_id, $hash){
            $conectar= parent::conexion();
            parent::set_names();
            $sql = "UPDATE tm_usuario SET usu_pass = ?, fech_modi = now() WHERE usu_id = ?";
            $stmt = $conectar->prepare($sql);
            $stmt->bindValue(1, $hash);
            $stmt->bindValue(2, (int)$usu_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        /* ¿Existe ya ese nombre de usuario? (opcionalmente excluyendo un usu_id, para la edición) */
        public function existe_usuario($usu_usuario, $excluir_usu_id = null){
            $conectar= parent::conexion();
            parent::set_names();
            $sql = "SELECT COUNT(*) AS total FROM tm_usuario WHERE usu_usuario = ?";
            if ($excluir_usu_id !== null && $excluir_usu_id !== ""){
                $sql .= " AND usu_id <> ?";
            }
            $stmt = $conectar->prepare($sql);
            $stmt->bindValue(1, $usu_usuario);
            if ($excluir_usu_id !== null && $excluir_usu_id !== ""){
                $stmt->bindValue(2, (int)$excluir_usu_id, PDO::PARAM_INT);
            }
            $stmt->execute();
            return (int)$stmt->fetchColumn() > 0;
        }

        public function login(){
            $conectar=parent::conexion();
            parent::set_names();
            if(isset($_POST["enviar"])){
                $usu_usuario = self::normalizar_usuario($_POST["usu_usuario"] ?? "");
                $pass = (string)($_POST["usu_pass"] ?? "");
                if($usu_usuario === "" || $pass === ""){
                    header("Location:".Conectar::ruta()."index.php?m=2");
					exit();
                }

                /* Demasiados intentos fallidos: bloqueo temporal */
                if (login_bloqueado($usu_usuario)){
                    header("Location:".Conectar::ruta()."index.php?m=3");
                    exit();
                }

                /* El rol se lee de la base de datos, no del formulario */
                $sql = "SELECT usu_id, usu_nom, usu_ape, rol_id, usu_pass FROM tm_usuario WHERE usu_usuario=? and est=1";
                $stmt=$conectar->prepare($sql);
                $stmt->bindValue(1, $usu_usuario);
                $stmt->execute();
                $fila = $stmt->fetch(PDO::FETCH_ASSOC);

                if (is_array($fila)){
                    $ok = $this->verificar_pass_usuario($fila["usu_id"], $pass, $fila["usu_pass"]);
                } else {
                    /* mismo coste que un login real, para no revelar qué usuarios existen */
                    password_verify($pass, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT));
                    $ok = false;
                }

                if ($ok){
                    login_exito($usu_usuario);
                    session_regenerate_id(true);
                    $_SESSION["usu_id"]=(int)$fila["usu_id"];
                    $_SESSION["usu_nom"]=$fila["usu_nom"];
                    $_SESSION["usu_ape"]=$fila["usu_ape"];
                    $_SESSION["rol_id"]=(int)$fila["rol_id"];
                    $_SESSION["bienvenida"]=true;   /* el Inicio muestra la animación de éxito una sola vez */
                    header("Location:".Conectar::ruta()."view/Home/");
                    exit();
                }
                login_fallo($usu_usuario);
                header("Location:".Conectar::ruta()."index.php?m=1");
                exit();
            }
        }

        public function insert_usuario($usu_nom,$usu_ape,$usu_usuario,$usu_correo,$usu_pass,$rol_id){
            $conectar= parent::conexion();
            parent::set_names();
            $sql="INSERT INTO tm_usuario (usu_id, usu_nom, usu_ape, usu_usuario, usu_correo, usu_pass, rol_id, fech_crea, fech_modi, fech_elim, est) VALUES (NULL,?,?,?,?,?,?,now(), NULL, NULL, '1');";
            $sql=$conectar->prepare($sql);
            $sql->bindValue(1, $usu_nom);
            $sql->bindValue(2, $usu_ape);
            $sql->bindValue(3, $usu_usuario);
            $sql->bindValue(4, $usu_correo);
            $sql->bindValue(5, self::hash_pass($usu_pass));
            $sql->bindValue(6, (int)$rol_id, PDO::PARAM_INT);
            $sql->execute();
            return $resultado=$sql->fetchAll();
        }

        /* Contraseña vacía = no cambiarla */
        public function update_usuario($usu_id,$usu_nom,$usu_ape,$usu_usuario,$usu_correo,$usu_pass,$rol_id){
            $conectar= parent::conexion();
            parent::set_names();
            $cambiar_pass = ((string)$usu_pass !== "");
            $sql="UPDATE tm_usuario set
                usu_nom = ?,
                usu_ape = ?,
                usu_usuario = ?,
                usu_correo = ?,
                ".($cambiar_pass ? "usu_pass = ?," : "")."
                rol_id = ?,
                fech_modi = now()
                WHERE
                usu_id = ?";
            $sql=$conectar->prepare($sql);
            $i = 1;
            $sql->bindValue($i++, $usu_nom);
            $sql->bindValue($i++, $usu_ape);
            $sql->bindValue($i++, $usu_usuario);
            $sql->bindValue($i++, $usu_correo);
            if ($cambiar_pass){
                $sql->bindValue($i++, self::hash_pass($usu_pass));
            }
            $sql->bindValue($i++, (int)$rol_id, PDO::PARAM_INT);
            $sql->bindValue($i++, (int)$usu_id, PDO::PARAM_INT);
            $sql->execute();
            return $resultado=$sql->fetchAll();
        }

        public function delete_usuario($usu_id){
            $conectar= parent::conexion();
            parent::set_names();
            $sql="call sp_d_usuario_01(?)";
            $sql=$conectar->prepare($sql);
            $sql->bindValue(1, $usu_id);
            $sql->execute();
            $sql->closeCursor();
            return array();
        }

        public function get_usuario(){
            $conectar= parent::conexion();
            parent::set_names();
            $sql="call sp_l_usuario_01()";
            $sql=$conectar->prepare($sql);
            $sql->execute();
            $resultado=$sql->fetchAll();
            $sql->closeCursor();
            return $resultado;
        }

        /* Lista para Mantenimiento: cuenta, sede principal y resumen de áreas */
        public function listar_con_areas(){
            $sql = parent::conexion()->prepare("
                SELECT u.usu_id, u.usu_nom, u.usu_ape, u.usu_usuario, u.usu_correo, u.rol_id,
                       s.sede_nom,
                       GROUP_CONCAT(DISTINCT CONCAT(a.area_nom, IF(f.fun_id = 1, ' (Responsable)', IF(f.fun_id = 2, ' (Suplente)', '')))
                                    ORDER BY f.fun_id, a.area_nom SEPARATOR '|') AS areas
                FROM tm_usuario u
                LEFT JOIN tm_sede s ON s.sede_id = u.sede_id
                LEFT JOIN td_area_usuario au ON au.usu_id = u.usu_id AND au.est = 1
                LEFT JOIN tm_area a ON a.area_id = au.area_id AND a.est = 1
                LEFT JOIN tm_funcion f ON f.fun_id = au.fun_id
                WHERE u.est = 1
                GROUP BY u.usu_id
                ORDER BY u.usu_nom, u.usu_ape");
            $sql->execute();
            return $sql->fetchAll(PDO::FETCH_ASSOC);
        }

        /* Datos mínimos de una cuenta activa, o null si no existe / está desactivada */
        public function cuenta_activa($usu_id){
            $sql = parent::conexion()->prepare("SELECT rol_id, usu_nom, usu_ape FROM tm_usuario WHERE usu_id = ? AND est = 1");
            $sql->execute(array((int)$usu_id));
            $f = $sql->fetch(PDO::FETCH_ASSOC);
            if (!$f) { return null; }
            $f["rol_id"] = (int)$f["rol_id"];
            return $f;
        }

        public function set_sede($usu_id, $sede_id){
            $sql = parent::conexion()->prepare("UPDATE tm_usuario SET sede_id = ? WHERE usu_id = ?");
            $sql->execute(array($sede_id ? (int)$sede_id : null, (int)$usu_id));
        }

        public function id_por_usuario($usu_usuario){
            $sql = parent::conexion()->prepare("SELECT usu_id FROM tm_usuario WHERE usu_usuario = ?");
            $sql->execute(array($usu_usuario));
            return (int)$sql->fetchColumn();
        }

        public function get_usuario_x_id($usu_id){
            $conectar= parent::conexion();
            parent::set_names();
            $sql="call sp_l_usuario_02(?)";
            $sql=$conectar->prepare($sql);
            $sql->bindValue(1, $usu_id);
            $sql->execute();
            $resultado=$sql->fetchAll();
            $sql->closeCursor();
            return $resultado;
        }


        /* Cambio desde el perfil: exige la contraseña actual. Devuelve true/false */
        public function cambiar_pass($usu_id, $pass_actual, $pass_nueva){
            $conectar= parent::conexion();
            parent::set_names();
            $stmt = $conectar->prepare("SELECT usu_pass FROM tm_usuario WHERE usu_id = ? AND est = 1");
            $stmt->bindValue(1, (int)$usu_id, PDO::PARAM_INT);
            $stmt->execute();
            $hash = $stmt->fetchColumn();
            if ($hash === false || self::comprobar_pass($pass_actual, $hash) === false){
                return false;
            }
            $this->guardar_hash($usu_id, self::hash_pass($pass_nueva));
            return true;
        }

    }
?>
