<?php
/* Reportes de actividad de soporte (tickets) para un período [desde, hasta].
   "Recibidos" = creados en el período; "Resueltos" = cerrados en el período. */
class Reporte extends Conectar {

    private $desde;
    private $hasta;   /* exclusivo: el día siguiente al último */

    public function __construct($desde, $hasta){
        $this->desde = $desde." 00:00:00";
        $this->hasta = date("Y-m-d", strtotime($hasta." +1 day"))." 00:00:00";
    }

    private function todos($sql, array $p = array()){
        $s = $this->Conexion()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function rango(){
        return array($this->desde, $this->hasta);
    }

    public function indicadores(){
        $r = $this->todos("
            SELECT
              (SELECT COUNT(*) FROM tm_ticket WHERE est = 1 AND fech_crea >= ? AND fech_crea < ?) AS recibidos,
              (SELECT COUNT(*) FROM tm_ticket WHERE est = 1 AND fech_cierre >= ? AND fech_cierre < ?) AS resueltos,
              (SELECT COUNT(*) FROM tm_ticket t INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
                WHERE t.est = 1 AND t.fech_cierre >= ? AND t.fech_cierre < ?
                  AND t.fech_cierre <= t.fech_crea + INTERVAL p.prio_sla_horas HOUR) AS en_sla,
              (SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, fech_crea, fech_cierre)) / 60, 1) FROM tm_ticket
                WHERE est = 1 AND fech_cierre >= ? AND fech_cierre < ?) AS horas_promedio,
              (SELECT COUNT(*) FROM tm_ticket WHERE est = 1 AND tick_estado <> 'Cerrado') AS pendientes,
              (SELECT COUNT(*) FROM tm_ticket t INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
                WHERE t.est = 1 AND t.tick_estado <> 'Cerrado'
                  AND now() > t.fech_crea + INTERVAL p.prio_sla_horas HOUR) AS vencidos",
            array_merge($this->rango(), $this->rango(), $this->rango(), $this->rango()));
        return $r[0];
    }

    /* Recibidos y resueltos por mes dentro del período */
    public function tendencia(){
        $recibidos = $this->todos("
            SELECT DATE_FORMAT(fech_crea, '%Y-%m') AS mes, COUNT(*) AS n FROM tm_ticket
            WHERE est = 1 AND fech_crea >= ? AND fech_crea < ? GROUP BY mes", $this->rango());
        $resueltos = $this->todos("
            SELECT DATE_FORMAT(fech_cierre, '%Y-%m') AS mes, COUNT(*) AS n FROM tm_ticket
            WHERE est = 1 AND fech_cierre >= ? AND fech_cierre < ? GROUP BY mes", $this->rango());
        $meses = array();
        $m = strtotime(substr($this->desde, 0, 7)."-01");
        $fin = strtotime($this->hasta." -1 second");
        while ($m <= $fin){
            $meses[date("Y-m", $m)] = array("mes" => date("Y-m", $m), "recibidos" => 0, "resueltos" => 0);
            $m = strtotime("+1 month", $m);
        }
        foreach ($recibidos as $r){ if (isset($meses[$r["mes"]])) { $meses[$r["mes"]]["recibidos"] = (int)$r["n"]; } }
        foreach ($resueltos as $r){ if (isset($meses[$r["mes"]])) { $meses[$r["mes"]]["resueltos"] = (int)$r["n"]; } }
        return array_values($meses);
    }

    /* Recibidos en el período agrupados por una columna de tm_ticket unida a su catálogo */
    private function agrupar($join, $nombre){
        return $this->todos("
            SELECT COALESCE(".$nombre.", 'Sin dato') AS nombre, COUNT(*) AS recibidos,
                   SUM(t.tick_estado = 'Cerrado') AS resueltos,
                   SUM(t.tick_estado <> 'Cerrado') AS pendientes
            FROM tm_ticket t ".$join."
            WHERE t.est = 1 AND t.fech_crea >= ? AND t.fech_crea < ?
            GROUP BY nombre ORDER BY recibidos DESC, nombre", $this->rango());
    }

    public function por_area(){
        return $this->agrupar("LEFT JOIN tm_area a ON a.area_id = t.area_id", "a.area_nom");
    }

    public function por_sede(){
        return $this->agrupar("LEFT JOIN tm_sede s ON s.sede_id = t.sede_id", "s.sede_nom");
    }

    public function por_categoria(){
        return $this->agrupar("LEFT JOIN tm_categoria c ON c.cat_id = t.cat_id", "c.cat_nom");
    }

    public function por_urgencia(){
        return $this->todos("
            SELECT p.prio_nom AS nombre, p.prio_id, COUNT(t.tick_id) AS recibidos
            FROM tm_prioridad p
            LEFT JOIN tm_ticket t ON t.prio_id = p.prio_id AND t.est = 1 AND t.fech_crea >= ? AND t.fech_crea < ?
            GROUP BY p.prio_id, p.prio_nom ORDER BY p.prio_id DESC", $this->rango());
    }

    /* Técnicos: asignados en el período, resueltos en el período, pendientes hoy y tiempo promedio */
    public function por_tecnico(){
        $p = array_merge($this->rango(), $this->rango(), $this->rango());
        return $this->todos("
            SELECT TRIM(CONCAT(u.usu_nom, ' ', u.usu_ape)) AS nombre,
                   SUM(t.fech_asig >= ? AND t.fech_asig < ?) AS asignados,
                   SUM(t.fech_cierre >= ? AND t.fech_cierre < ?) AS resueltos,
                   SUM(t.tick_estado <> 'Cerrado') AS pendientes,
                   ROUND(AVG(CASE WHEN t.fech_cierre >= ? AND t.fech_cierre < ?
                                  THEN TIMESTAMPDIFF(MINUTE, t.fech_crea, t.fech_cierre) END) / 60, 1) AS horas_promedio
            FROM tm_ticket t INNER JOIN tm_usuario u ON u.usu_id = t.usu_asig
            WHERE t.est = 1
            GROUP BY u.usu_id
            HAVING asignados > 0 OR resueltos > 0 OR pendientes > 0
            ORDER BY resueltos DESC, asignados DESC", $p);
    }
}
