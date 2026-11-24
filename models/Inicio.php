<?php
/* Datos de la pantalla de Inicio según el rol.
   Cuando exista la tabla de áreas, aquí se añadirá el filtro por área. */
class Inicio extends Conectar {

    private function uno($sql, array $params = array()){
        $stmt = $this->Conexion()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: array();
    }

    private function todos($sql, array $params = array()){
        $stmt = $this->Conexion()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* Columnas comunes de un ticket para las tablas del Inicio */
    private const CAMPOS_TICKET = "
        t.tick_id, t.tick_titulo, t.tick_estado, t.fech_crea,
        c.cat_nom,
        TRIM(CONCAT(COALESCE(a.usu_nom,''),' ',COALESCE(a.usu_ape,''))) AS asignado
        FROM tm_ticket t
        INNER JOIN tm_categoria c ON c.cat_id = t.cat_id
        LEFT JOIN tm_usuario a ON a.usu_id = t.usu_asig";

    /* Administrador del sistema: panel de administración (qué requiere atención).
       La actividad de tickets está en Reportes y la lista completa en Tickets. */
    public function resumen_admin(){
        $totales = $this->uno("
            SELECT
              (SELECT COUNT(*) FROM tm_usuario WHERE est = 1) AS cuentas,
              (SELECT COUNT(*) FROM tm_usuario WHERE est = 1 AND usu_pass_temporal = 1
                  AND (usu_pass_expira IS NULL OR usu_pass_expira >= now())) AS temporales,
              (SELECT COUNT(*) FROM tm_usuario WHERE est = 1 AND usu_pass_temporal = 1 AND usu_pass_expira < now()) AS vencidas,
              (SELECT COUNT(*) FROM tm_usuario u WHERE u.est = 1 AND NOT EXISTS
                  (SELECT 1 FROM td_area_usuario au WHERE au.usu_id = u.usu_id AND au.est = 1)) AS sin_area,
              (SELECT COUNT(*) FROM tm_ticket t INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
                WHERE t.est = 1 AND t.tick_estado <> 'Cerrado'
                  AND now() > t.fech_crea + INTERVAL p.prio_sla_horas HOUR) AS fuera_tiempo,
              (SELECT COUNT(*) FROM tm_ticket WHERE est = 1 AND tick_estado <> 'Cerrado' AND usu_asig IS NULL) AS sin_asignar");

        $sin_area = $this->todos("
            SELECT u.usu_id, TRIM(CONCAT(u.usu_nom, ' ', u.usu_ape)) AS nombre, u.usu_usuario AS usuario
            FROM tm_usuario u WHERE u.est = 1 AND NOT EXISTS
              (SELECT 1 FROM td_area_usuario au WHERE au.usu_id = u.usu_id AND au.est = 1)
            ORDER BY u.usu_nom LIMIT 6");

        $vencidas = $this->todos("
            SELECT TRIM(CONCAT(usu_nom, ' ', usu_ape)) AS nombre, usu_usuario AS usuario, usu_pass_expira AS fecha
            FROM tm_usuario WHERE est = 1 AND usu_pass_temporal = 1 AND usu_pass_expira < now()
            ORDER BY usu_pass_expira DESC LIMIT 6");

        $fuera_tiempo = $this->todos("
            SELECT t.tick_id, t.tick_titulo, t.fech_crea, p.prio_nom, p.prio_id,
                   TIMESTAMPDIFF(HOUR, t.fech_crea + INTERVAL p.prio_sla_horas HOUR, now()) AS horas_vencido,
                   TRIM(CONCAT(COALESCE(a.usu_nom, ''), ' ', COALESCE(a.usu_ape, ''))) AS asignado
            FROM tm_ticket t INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
            LEFT JOIN tm_usuario a ON a.usu_id = t.usu_asig
            WHERE t.est = 1 AND t.tick_estado <> 'Cerrado' AND now() > t.fech_crea + INTERVAL p.prio_sla_horas HOUR
            ORDER BY p.prio_id DESC, horas_vencido DESC LIMIT 6");

        $areas_vacias = $this->todos("
            SELECT a.area_nom AS nombre FROM tm_area a
            WHERE a.est = 1 AND a.area_estado = 'activa' AND NOT EXISTS
              (SELECT 1 FROM td_area_usuario au WHERE au.area_id = a.area_id AND au.est = 1)
            ORDER BY a.area_nom");

        return array("totales" => $totales, "sin_area" => $sin_area, "vencidas" => $vencidas,
                     "fuera_tiempo" => $fuera_tiempo, "areas_vacias" => $areas_vacias);
    }

    /* Soporte: su trabajo asignado y la cola sin asignar */
    public function resumen_soporte($usu_id){
        $totales = $this->uno("
            SELECT COALESCE(SUM(tick_estado<>'Cerrado' AND usu_asig=?),0) AS mis_abiertos,
                   COALESCE(SUM(tick_estado<>'Cerrado' AND usu_asig IS NULL),0) AS sin_asignar,
                   COALESCE(SUM(tick_estado='Cerrado' AND usu_asig=?),0) AS mis_cerrados,
                   COALESCE(SUM(tick_estado<>'Cerrado'),0) AS abiertos
            FROM tm_ticket WHERE est=1", array($usu_id, $usu_id));

        $asignados = $this->todos("SELECT ".self::CAMPOS_TICKET."
            WHERE t.est=1 AND t.tick_estado<>'Cerrado' AND t.usu_asig=?
            ORDER BY t.fech_crea ASC LIMIT 8", array($usu_id));

        $sin_asignar = $this->todos("SELECT ".self::CAMPOS_TICKET."
            WHERE t.est=1 AND t.tick_estado<>'Cerrado' AND t.usu_asig IS NULL
            ORDER BY t.fech_crea ASC LIMIT 8");

        return array("totales" => $totales, "asignados" => $asignados, "sin_asignar" => $sin_asignar);
    }

    /* Usuario: solo sus propios tickets */
    public function resumen_usuario($usu_id){
        $totales = $this->uno("
            SELECT COUNT(*) AS total,
                   COALESCE(SUM(tick_estado<>'Cerrado'),0) AS abiertos,
                   COALESCE(SUM(tick_estado='Cerrado'),0) AS cerrados
            FROM tm_ticket WHERE est=1 AND usu_id=?", array($usu_id));

        $recientes = $this->todos("SELECT ".self::CAMPOS_TICKET."
            WHERE t.est=1 AND t.usu_id=?
            ORDER BY t.fech_crea DESC, t.tick_id DESC LIMIT 8", array($usu_id));

        return array("totales" => $totales, "recientes" => $recientes);
    }
}
