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

    /* Administrador: visión general de todo el sistema */
    public function resumen_admin(){
        $totales = $this->uno("
            SELECT COUNT(*) AS total,
                   COALESCE(SUM(tick_estado='Abierto'),0) AS abiertos,
                   COALESCE(SUM(tick_estado='Cerrado'),0) AS cerrados,
                   COALESCE(SUM(tick_estado='Abierto' AND usu_asig IS NULL),0) AS sin_asignar
            FROM tm_ticket WHERE est=1");

        $categorias = $this->todos("
            SELECT c.cat_nom AS nombre, COUNT(t.tick_id) AS total
            FROM tm_categoria c
            LEFT JOIN tm_ticket t ON t.cat_id = c.cat_id AND t.est=1
            WHERE c.est=1
            GROUP BY c.cat_id, c.cat_nom
            ORDER BY total DESC, c.cat_nom");

        $recientes = $this->todos("SELECT ".self::CAMPOS_TICKET."
            WHERE t.est=1 ORDER BY t.fech_crea DESC, t.tick_id DESC LIMIT 6");

        return array("totales" => $totales, "categorias" => $categorias, "recientes" => $recientes);
    }

    /* Soporte: su trabajo asignado y la cola sin asignar */
    public function resumen_soporte($usu_id){
        $totales = $this->uno("
            SELECT COALESCE(SUM(tick_estado='Abierto' AND usu_asig=?),0) AS mis_abiertos,
                   COALESCE(SUM(tick_estado='Abierto' AND usu_asig IS NULL),0) AS sin_asignar,
                   COALESCE(SUM(tick_estado='Cerrado' AND usu_asig=?),0) AS mis_cerrados,
                   COALESCE(SUM(tick_estado='Abierto'),0) AS abiertos
            FROM tm_ticket WHERE est=1", array($usu_id, $usu_id));

        $asignados = $this->todos("SELECT ".self::CAMPOS_TICKET."
            WHERE t.est=1 AND t.tick_estado='Abierto' AND t.usu_asig=?
            ORDER BY t.fech_crea ASC LIMIT 8", array($usu_id));

        $sin_asignar = $this->todos("SELECT ".self::CAMPOS_TICKET."
            WHERE t.est=1 AND t.tick_estado='Abierto' AND t.usu_asig IS NULL
            ORDER BY t.fech_crea ASC LIMIT 8");

        return array("totales" => $totales, "asignados" => $asignados, "sin_asignar" => $sin_asignar);
    }

    /* Usuario: solo sus propios tickets */
    public function resumen_usuario($usu_id){
        $totales = $this->uno("
            SELECT COUNT(*) AS total,
                   COALESCE(SUM(tick_estado='Abierto'),0) AS abiertos,
                   COALESCE(SUM(tick_estado='Cerrado'),0) AS cerrados
            FROM tm_ticket WHERE est=1 AND usu_id=?", array($usu_id));

        $recientes = $this->todos("SELECT ".self::CAMPOS_TICKET."
            WHERE t.est=1 AND t.usu_id=?
            ORDER BY t.fech_crea DESC, t.tick_id DESC LIMIT 8", array($usu_id));

        return array("totales" => $totales, "recientes" => $recientes);
    }
}
