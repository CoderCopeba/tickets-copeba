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
            /* Solo cuentas activas: el nombre de una cuenta eliminada se puede volver a usar */
            $sql = "SELECT COUNT(*) AS total FROM tm_usuario WHERE usu_usuario = ? AND est = 1";
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
                $sql = "SELECT usu_id, usu_nom, usu_ape, rol_id, usu_pass, usu_pass_temporal,
                               (usu_pass_expira IS NOT NULL AND usu_pass_expira < now()) AS vencida
                        FROM tm_usuario WHERE usu_usuario=? and est=1";
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

                /* Temporal vencida: no sirve, hay que pedir otra a Informática */
                if ($ok && (int)$fila["usu_pass_temporal"] === 1 && (int)$fila["vencida"] === 1){
                    header("Location:".Conectar::ruta()."index.php?m=5");
                    exit();
                }

                if ($ok){
                    login_exito($usu_usuario);
                    session_regenerate_id(true);
                    $_SESSION["usu_id"]=(int)$fila["usu_id"];
                    $_SESSION["usu_nom"]=$fila["usu_nom"];
                    $_SESSION["usu_ape"]=$fila["usu_ape"];
                    $_SESSION["rol_id"]=(int)$fila["rol_id"];
                    $_SESSION["bienvenida"]=true;   /* el Inicio muestra la animación de éxito una sola vez */
                    /* Con temporal: primero debe crear su propia contraseña */
                    $destino = (int)$fila["usu_pass_temporal"] === 1 ? "view/CambiarPass/" : "view/Home/";
                    header("Location:".Conectar::ruta().$destino);
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

        /* Baja lógica (la cuenta se conserva por el historial). Antes: procedimiento sp_d_usuario_01 */
        public function delete_usuario($usu_id){
            /* La cuenta se desactiva (se conservan su historial y sus tickets) y su nombre de
               usuario queda libre: "juan" pasa a "juan~15" (el "~" no es válido en un nombre
               real, así que nunca choca con uno nuevo; caben 30 caracteres). */
            $sql = parent::conexion()->prepare("
                UPDATE tm_usuario SET est = 0, fech_elim = now(),
                       usu_usuario = CONCAT(LEFT(usu_usuario, 29 - CHAR_LENGTH(usu_id)), '~', usu_id)
                WHERE usu_id = ? AND est = 1");
            $sql->execute(array((int)$usu_id));
            return array();
        }

        /* Cuentas eliminadas ANTES de este cambio conservan su nombre: se libera al reutilizarlo */
        public function liberar_usuario_inactivo($usu_usuario){
            parent::conexion()->prepare("
                UPDATE tm_usuario SET usu_usuario = CONCAT(LEFT(usu_usuario, 29 - CHAR_LENGTH(usu_id)), '~', usu_id)
                WHERE usu_usuario = ? AND est = 0")->execute(array((string)$usu_usuario));
        }

        /* Cuentas activas, sin la contraseña. Antes: procedimiento sp_l_usuario_01 */
        public function get_usuario(){
            $sql = parent::conexion()->prepare("SELECT usu_id, usu_nom, usu_ape, usu_usuario, usu_correo, rol_id, sede_id, fech_crea
                                                FROM tm_usuario WHERE est = 1 ORDER BY usu_nom, usu_ape");
            $sql->execute();
            return $sql->fetchAll();
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
                GROUP BY u.usu_id, s.sede_nom
                ORDER BY u.usu_nom, u.usu_ape");
            $sql->execute();
            return $sql->fetchAll(PDO::FETCH_ASSOC);
        }

        /* Datos mínimos de una cuenta activa, o null si no existe / está desactivada.
           En la MISMA consulta trae si atiende tickets (área que atiende) y si es
           responsable de $area_reportes: así cada petición valida sesión y permisos
           con un solo viaje a la base (cada viaje a la nube cuesta ~150 ms). */
        public function cuenta_activa($usu_id, $area_reportes = ""){
            $sql = parent::conexion()->prepare("
                SELECT u.rol_id, u.usu_nom, u.usu_ape, u.usu_pass_temporal,
                       EXISTS(SELECT 1 FROM td_area_usuario au INNER JOIN tm_area a ON a.area_id = au.area_id
                              WHERE au.usu_id = u.usu_id AND au.est = 1 AND a.est = 1 AND a.area_atiende = 1) AS atiende,
                       EXISTS(SELECT 1 FROM td_area_usuario au INNER JOIN tm_area a ON a.area_id = au.area_id
                              WHERE au.usu_id = u.usu_id AND au.est = 1 AND au.fun_id = 1 AND a.area_nom = ?) AS responsable_reportes
                FROM tm_usuario u
                WHERE u.usu_id = ? AND u.est = 1");
            $sql->execute(array((string)$area_reportes, (int)$usu_id));
            $f = $sql->fetch(PDO::FETCH_ASSOC);
            if (!$f) { return null; }
            $f["rol_id"] = (int)$f["rol_id"];
            $f["atiende"] = (int)$f["atiende"] === 1;
            $f["responsable_reportes"] = (int)$f["responsable_reportes"] === 1;
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

        /* Una cuenta (sin la contraseña). Antes: procedimiento sp_l_usuario_02 */
        public function get_usuario_x_id($usu_id){
            $sql = parent::conexion()->prepare("SELECT usu_id, usu_nom, usu_ape, usu_usuario, usu_correo, rol_id, sede_id, est
                                                FROM tm_usuario WHERE usu_id = ?");
            $sql->execute(array((int)$usu_id));
            return $sql->fetchAll();
        }


        /* Cambio desde el perfil: exige la contraseña actual. Devuelve true/false */
        /* ===== Contraseñas temporales y control de cuentas ===== */
        const HORAS_TEMPORAL = 24;     /* vigencia de una temporal generada por Informática */
        const PASS_MINIMO = 8;

        /* Contraseña temporal al azar, fácil de dictar: sin 0/O ni 1/I/L. Ej.: K7QM-4XPT-9HWD */
        public static function generar_texto_temporal(){
            $alfabeto = "ABCDEFGHJKMNPQRSTUVWXYZ23456789";
            $grupos = array();
            for ($g = 0; $g < 3; $g++){
                $grupo = "";
                for ($i = 0; $i < 4; $i++){
                    $grupo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                }
                $grupos[] = $grupo;
            }
            return implode("-", $grupos);
        }

        public function registrar_evento($usu_id, $actor_id, $evento, $detalle = null){
            parent::conexion()->prepare("INSERT INTO td_usuario_evento (usu_id, actor_id, ue_evento, ue_detalle) VALUES (?, ?, ?, ?)")
                ->execute(array((int)$usu_id, $actor_id ? (int)$actor_id : null, $evento, $detalle));
        }

        /* Guarda una temporal (solo su hash). La persona deberá cambiarla al entrar.
           Devuelve el texto en claro UNA sola vez, para dictarlo; no se guarda en ningún lado. */
        public function generar_temporal($usu_id, $actor_id, $horas = self::HORAS_TEMPORAL){
            $temporal = self::generar_texto_temporal();
            $this->marcar_temporal($usu_id, $temporal, $horas);
            $this->registrar_evento($usu_id, $actor_id, "temporal_generada", "Vigente ".(int)$horas." h");
            return $temporal;
        }

        public function marcar_temporal($usu_id, $pass, $horas){
            parent::conexion()->prepare("
                UPDATE tm_usuario SET usu_pass = ?, usu_pass_temporal = 1,
                       usu_pass_expira = now() + INTERVAL ? HOUR, fech_modi = now()
                WHERE usu_id = ?")
                ->execute(array(self::hash_pass($pass), (int)$horas, (int)$usu_id));
        }

        /* Contraseña definitiva elegida por la persona */
        public function definir_pass($usu_id, $pass, $evento){
            parent::conexion()->prepare("
                UPDATE tm_usuario SET usu_pass = ?, usu_pass_temporal = 0, usu_pass_expira = NULL,
                       fech_pass = now(), fech_modi = now()
                WHERE usu_id = ?")
                ->execute(array(self::hash_pass($pass), (int)$usu_id));
            $this->registrar_evento($usu_id, $usu_id, $evento);
        }

        /* ¿Coincide con la contraseña guardada? (para no "cambiar" a la misma temporal) */
        public function es_pass_actual($usu_id, $pass){
            $stmt = parent::conexion()->prepare("SELECT usu_pass FROM tm_usuario WHERE usu_id = ?");
            $stmt->execute(array((int)$usu_id));
            $hash = $stmt->fetchColumn();
            return $hash !== false && self::comprobar_pass($pass, $hash) !== false;
        }

        /* Lista para el control de cuentas de Informática (sin contraseñas, solo su estado) */
        public function control_lista(){
            $sql = parent::conexion()->prepare("
                SELECT u.usu_id, u.usu_nom, u.usu_ape, u.usu_usuario, u.rol_id,
                       u.usu_pass_temporal, u.usu_pass_expira, u.fech_pass, s.sede_nom,
                       (u.usu_pass_expira IS NOT NULL AND u.usu_pass_expira < now()) AS vencida,
                       GROUP_CONCAT(DISTINCT a.area_nom ORDER BY a.area_nom SEPARATOR ', ') AS areas,
                       (SELECT CONCAT(e.ue_evento, '|', e.fech_crea, '|', COALESCE(CONCAT(x.usu_nom, ' ', x.usu_ape), ''))
                          FROM td_usuario_evento e LEFT JOIN tm_usuario x ON x.usu_id = e.actor_id
                         WHERE e.usu_id = u.usu_id ORDER BY e.ue_id DESC LIMIT 1) AS ultimo
                FROM tm_usuario u
                LEFT JOIN tm_sede s ON s.sede_id = u.sede_id
                LEFT JOIN td_area_usuario au ON au.usu_id = u.usu_id AND au.est = 1
                LEFT JOIN tm_area a ON a.area_id = au.area_id AND a.est = 1
                WHERE u.est = 1
                GROUP BY u.usu_id, s.sede_nom
                ORDER BY u.usu_pass_temporal DESC, u.usu_nom, u.usu_ape");
            $sql->execute();
            return $sql->fetchAll(PDO::FETCH_ASSOC);
        }

        public function historial($usu_id, $limite = 15){
            $sql = parent::conexion()->prepare("
                SELECT e.ue_evento, e.ue_detalle, e.fech_crea, TRIM(CONCAT(COALESCE(x.usu_nom,''), ' ', COALESCE(x.usu_ape,''))) AS actor
                FROM td_usuario_evento e LEFT JOIN tm_usuario x ON x.usu_id = e.actor_id
                WHERE e.usu_id = ? ORDER BY e.ue_id DESC LIMIT ".(int)$limite);
            $sql->execute(array((int)$usu_id));
            return $sql->fetchAll(PDO::FETCH_ASSOC);
        }

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
            $this->definir_pass($usu_id, $pass_nueva, "pass_cambiada");
            return true;
        }

    }
?>
