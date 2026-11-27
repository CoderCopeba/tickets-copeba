<?php
    class Ticket extends Conectar{

        /* $area_id / $sede_id: de dónde sale el ticket (foto del área del solicitante al crearlo) */
        public function insert_ticket($usu_id,$cat_id,$tick_titulo,$tick_descrip,$prio_id = 2,$area_id = null,$sede_id = null){
            $conectar= parent::conexion();
            $sql=$conectar->prepare("INSERT INTO tm_ticket (usu_id,cat_id,prio_id,area_id,sede_id,tick_titulo,tick_descrip,tick_estado,fech_crea,usu_asig,fech_asig,est)
                                     VALUES (?,?,?,?,?,?,?,'Abierto',now(),NULL,NULL,1)");
            $sql->execute(array((int)$usu_id, (int)$cat_id, (int)$prio_id,
                                $area_id ? (int)$area_id : null, $sede_id ? (int)$sede_id : null,
                                $tick_titulo, $tick_descrip));

            $sql1="select last_insert_id() as 'tick_id';";
            $sql1=$conectar->prepare($sql1);
            $sql1->execute();
            return $resultado=$sql1->fetchAll(pdo::FETCH_ASSOC);
        }

        /* usu_id del creador del ticket, o null si no existe */
        public function get_ticket_propietario($tick_id){
            $conectar= parent::conexion();
            parent::set_names();
            $sql="SELECT usu_id FROM tm_ticket WHERE tick_id = ? AND est = 1";
            $sql=$conectar->prepare($sql);
            $sql->bindValue(1, (int)$tick_id, PDO::PARAM_INT);
            $sql->execute();
            $usu_id = $sql->fetchColumn();
            return ($usu_id === false) ? null : (int)$usu_id;
        }

        /* Lista para la pantalla Tickets: todos (null) o los de una persona. Una sola consulta. */
        public function lista($usu_id = null){
            $sql = parent::conexion()->prepare("
                SELECT t.tick_id, t.tick_titulo, t.tick_estado, t.fech_crea, t.fech_asig, t.usu_asig,
                       c.cat_nom, p.prio_id, p.prio_nom,
                       (t.tick_estado <> 'Cerrado' AND now() > t.fech_crea + INTERVAL p.prio_sla_horas HOUR) AS vencido,
                       TRIM(CONCAT(u.usu_nom, ' ', u.usu_ape)) AS solicitante,
                       TRIM(CONCAT(COALESCE(a.usu_nom, ''), ' ', COALESCE(a.usu_ape, ''))) AS asignado
                FROM tm_ticket t
                INNER JOIN tm_categoria c ON c.cat_id = t.cat_id
                INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
                INNER JOIN tm_usuario u ON u.usu_id = t.usu_id
                LEFT JOIN tm_usuario a ON a.usu_id = t.usu_asig
                WHERE t.est = 1".($usu_id === null ? "" : " AND t.usu_id = ?")."
                ORDER BY t.tick_id DESC");
            $sql->execute($usu_id === null ? array() : array((int)$usu_id));
            return $sql->fetchAll(PDO::FETCH_ASSOC);
        }

        public function listar_ticket_x_id($tick_id){
            $conectar= parent::conexion();
            parent::set_names();
            $sql="SELECT 
                tm_ticket.tick_id,
                tm_ticket.usu_id,
                tm_ticket.cat_id,
                tm_ticket.tick_titulo,
                tm_ticket.tick_descrip,
                tm_ticket.tick_estado,
                tm_ticket.fech_crea,
                tm_usuario.usu_nom,
                tm_usuario.usu_ape,
                tm_usuario.usu_correo,
                tm_categoria.cat_nom
                FROM 
                tm_ticket
                INNER join tm_categoria on tm_ticket.cat_id = tm_categoria.cat_id
                INNER join tm_usuario on tm_ticket.usu_id = tm_usuario.usu_id
                WHERE
                tm_ticket.est = 1
                AND tm_ticket.tick_id = ?";
            $sql=$conectar->prepare($sql);
            $sql->bindValue(1, $tick_id);
            $sql->execute();
            return $resultado=$sql->fetchAll();
        }

        /* ===== Bitácora: respuesta (visible al usuario), nota (interna TI) o evento ===== */
        const TIPOS_BITACORA = array("respuesta", "nota", "evento");

        /* Devuelve el id de la nueva entrada */
        public function insert_ticketdetalle($tick_id, $usu_id, $tickd_descrip, $tipo = "respuesta"){
            $tipo = in_array($tipo, self::TIPOS_BITACORA, true) ? $tipo : "respuesta";
            $conectar = parent::conexion();
            $sql = $conectar->prepare("INSERT INTO td_ticketdetalle (tick_id, usu_id, tickd_tipo, tickd_descrip, fech_crea, est) VALUES (?, ?, ?, ?, now(), 1)");
            $sql->execute(array((int)$tick_id, (int)$usu_id, $tipo, $tickd_descrip));
            return (int)$conectar->lastInsertId();
        }

        /* Evento automático (texto plano, se escapa al guardarlo) */
        public function insert_evento($tick_id, $usu_id, $texto){
            return $this->insert_ticketdetalle($tick_id, $usu_id, e($texto), "evento");
        }

        /* Estados válidos: Abierto, En espera, Cerrado */
        const ESTADOS = array("Abierto", "En espera", "Cerrado");

        public function cambiar_estado($tick_id, $estado){
            if (!in_array($estado, self::ESTADOS, true)) { return false; }
            /* fech_cierre: se marca al cerrar y se borra al reabrir (para tiempos de resolución) */
            $sql = parent::conexion()->prepare("UPDATE tm_ticket SET tick_estado = ?,
                       fech_cierre = IF(? = 'Cerrado', now(), NULL) WHERE tick_id = ?");
            return $sql->execute(array($estado, $estado, (int)$tick_id));
        }

        public function update_ticket_asignacion($tick_id, $usu_asig){
            $sql = parent::conexion()->prepare("UPDATE tm_ticket SET usu_asig = ?, fech_asig = now() WHERE tick_id = ?");
            return $sql->execute(array((int)$usu_asig, (int)$tick_id));
        }

        /* Toma el ticket solo si sigue libre: el WHERE lo hace atómico, así dos agentes
           que pulsan a la vez no se lo quedan los dos. Devuelve true si lo obtuvo. */
        public function tomar_si_libre($tick_id, $usu_id){
            $sql = parent::conexion()->prepare("UPDATE tm_ticket SET usu_asig = ?, fech_asig = now()
                       WHERE tick_id = ? AND usu_asig IS NULL AND tick_estado <> 'Cerrado'");
            $sql->execute(array((int)$usu_id, (int)$tick_id));
            return $sql->rowCount() > 0;
        }

        /* Todo lo que muestra la pantalla de detalle (un solo registro) */
        public function detalle($tick_id){
            $sql = parent::conexion()->prepare("
                SELECT t.tick_id, t.usu_id, t.tick_titulo, t.tick_descrip, t.tick_estado,
                       t.fech_crea, t.usu_asig, t.fech_asig,
                       c.cat_nom,
                       p.prio_id, p.prio_nom, p.prio_sla_horas,
                       u.usu_nom, u.usu_ape, u.usu_correo,
                       a.usu_nom AS asig_nom, a.usu_ape AS asig_ape,
                       (SELECT MAX(d.fech_crea) FROM td_ticketdetalle d
                         WHERE d.tick_id = t.tick_id AND d.tickd_tipo = 'evento'
                           AND d.tickd_descrip LIKE 'Ticket cerrado%') AS fech_cierre
                FROM tm_ticket t
                INNER JOIN tm_categoria c ON c.cat_id = t.cat_id
                INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
                INNER JOIN tm_usuario u ON u.usu_id = t.usu_id
                LEFT JOIN tm_usuario a ON a.usu_id = t.usu_asig
                WHERE t.tick_id = ? AND t.est = 1");
            $sql->execute(array((int)$tick_id));
            $fila = $sql->fetch(PDO::FETCH_ASSOC);
            return is_array($fila) ? $fila : null;
        }

        /* Entradas de la bitácora; las notas internas solo si $con_notas */
        public function bitacora($tick_id, $con_notas){
            $sql = parent::conexion()->prepare("
                SELECT d.tickd_id, d.tickd_tipo, d.tickd_descrip, d.fech_crea,
                       u.usu_id, u.usu_nom, u.usu_ape, u.rol_id,
                       (u.rol_id = 3 OR EXISTS (SELECT 1 FROM td_area_usuario au
                            INNER JOIN tm_area a ON a.area_id = au.area_id
                            WHERE au.usu_id = u.usu_id AND au.est = 1 AND a.area_atiende = 1)) AS es_ti
                FROM td_ticketdetalle d
                INNER JOIN tm_usuario u ON u.usu_id = d.usu_id
                WHERE d.tick_id = ? AND d.est = 1".($con_notas ? "" : " AND d.tickd_tipo <> 'nota'")."
                ORDER BY d.fech_crea, d.tickd_id");
            $sql->execute(array((int)$tick_id));
            return $sql->fetchAll(PDO::FETCH_ASSOC);
        }


    }
?>