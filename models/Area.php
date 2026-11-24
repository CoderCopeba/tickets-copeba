<?php
/* Áreas, sedes, funciones y pertenencias (modelo tipo Canvas:
   el rol de trabajo de una persona es POR ÁREA y POR SEDE). */
class Area extends Conectar {

    private function todos($sql, array $p = array()){
        $s = $this->Conexion()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function valor($sql, array $p = array()){
        $s = $this->Conexion()->prepare($sql);
        $s->execute($p);
        return $s->fetchColumn();
    }

    /* ¿La persona pertenece a un área activa que atiende tickets? */
    public function atiende_tickets($usu_id){
        return (int)$this->valor("
            SELECT COUNT(*) FROM td_area_usuario au
            INNER JOIN tm_area a ON a.area_id = au.area_id
            WHERE au.usu_id = ? AND au.est = 1 AND a.est = 1 AND a.area_atiende = 1", array((int)$usu_id)) > 0;
    }

    /* Personas que pueden recibir tickets: administradores del sistema y miembros de áreas que atienden */
    public function agentes(){
        return $this->todos("
            SELECT DISTINCT u.usu_id, u.usu_nom, u.usu_ape
            FROM tm_usuario u
            LEFT JOIN td_area_usuario au ON au.usu_id = u.usu_id AND au.est = 1
            LEFT JOIN tm_area a ON a.area_id = au.area_id AND a.est = 1
            WHERE u.est = 1 AND (u.rol_id = ? OR a.area_atiende = 1)
            ORDER BY u.usu_nom, u.usu_ape", array(ROL_ADMIN));
    }

    public function es_agente($usu_id){
        foreach ($this->agentes() as $a) {
            if ((int)$a["usu_id"] === (int)$usu_id) { return true; }
        }
        return false;
    }

    /* Área y sede principales de una persona (Responsable antes que Colaborador);
       si no tiene área, solo su sede principal */
    public function area_principal($usu_id){
        $f = $this->todos("
            SELECT au.area_id, au.sede_id FROM td_area_usuario au
            INNER JOIN tm_area a ON a.area_id = au.area_id AND a.est = 1
            WHERE au.usu_id = ? AND au.est = 1 ORDER BY au.fun_id, au.au_id LIMIT 1", array((int)$usu_id));
        if ($f) { return $f[0]; }
        return array("area_id" => null, "sede_id" => $this->valor("SELECT sede_id FROM tm_usuario WHERE usu_id = ?", array((int)$usu_id)) ?: null);
    }

    /* ¿Es Responsable de esa área (en cualquier sede)? */
    public function es_responsable($usu_id, $area_nom){
        return (int)$this->valor("
            SELECT COUNT(*) FROM td_area_usuario au INNER JOIN tm_area a ON a.area_id = au.area_id
            WHERE au.usu_id = ? AND au.est = 1 AND au.fun_id = 1 AND a.area_nom = ?", array((int)$usu_id, $area_nom)) > 0;
    }

    public function id_area($nombre){
        return (int)$this->valor("SELECT area_id FROM tm_area WHERE area_nom = ? AND est = 1", array($nombre));
    }

    public function id_sede_administrativa(){
        return (int)$this->valor("SELECT sede_id FROM tm_sede WHERE sede_tipo IN ('central', 'administrativa') AND est = 1 ORDER BY sede_id LIMIT 1");
    }

    /* ---------- Catálogos ---------- */
    public function sedes(){
        return $this->todos("SELECT sede_id, sede_nom, sede_tipo, sede_municipio, sede_departamento,
                (SELECT COUNT(DISTINCT au.usu_id) FROM td_area_usuario au WHERE au.sede_id = s.sede_id AND au.est = 1) AS miembros
            FROM tm_sede s WHERE est = 1 ORDER BY sede_id");
    }

    public function funciones(){
        return $this->todos("SELECT fun_id, fun_nom, fun_descrip FROM tm_funcion ORDER BY fun_id");
    }

    /* Áreas con su área padre, el área que las cubre y el número de miembros */
    public function areas(){
        return $this->todos("
            SELECT a.area_id, a.area_nom, a.area_descrip, a.area_estado, a.area_por_sede, a.area_atiende,
                   a.area_padre_id, p.area_nom AS padre_nom,
                   a.area_cargo_id, c.area_nom AS cargo_nom,
                   (SELECT COUNT(*) FROM td_area_usuario au WHERE au.area_id = a.area_id AND au.est = 1) AS miembros
            FROM tm_area a
            LEFT JOIN tm_area p ON p.area_id = a.area_padre_id
            LEFT JOIN tm_area c ON c.area_id = a.area_cargo_id
            WHERE a.est = 1
            ORDER BY a.area_estado, COALESCE(p.area_nom, a.area_nom), a.area_padre_id IS NOT NULL, a.area_nom");
    }

    public function area($area_id){
        $f = $this->todos("SELECT area_id, area_nom, area_descrip, area_estado, area_por_sede, area_atiende,
                                  area_padre_id, area_cargo_id
                           FROM tm_area WHERE area_id = ? AND est = 1", array((int)$area_id));
        return $f[0] ?? null;
    }

    public function existe($tabla, $id){
        $permitidas = array("tm_area" => "area_id", "tm_sede" => "sede_id", "tm_funcion" => "fun_id", "tm_usuario" => "usu_id");
        if (!isset($permitidas[$tabla])) { return false; }
        $extra = $tabla === "tm_funcion" ? "" : " AND est = 1";
        return (int)$this->valor("SELECT COUNT(*) FROM ".$tabla." WHERE ".$permitidas[$tabla]." = ?".$extra, array((int)$id)) > 0;
    }

    /* Crea o actualiza un área. Devuelve el id. */
    public function guardar_area(array $d){
        $cn = $this->Conexion();
        $valores = array($d["area_nom"], $d["area_descrip"], $d["area_padre_id"], $d["area_cargo_id"],
                         $d["area_estado"], $d["area_por_sede"], $d["area_atiende"]);
        if (empty($d["area_id"])) {
            $cn->prepare("INSERT INTO tm_area (area_nom, area_descrip, area_padre_id, area_cargo_id, area_estado, area_por_sede, area_atiende)
                          VALUES (?, ?, ?, ?, ?, ?, ?)")->execute($valores);
            return (int)$cn->lastInsertId();
        }
        $valores[] = (int)$d["area_id"];
        $cn->prepare("UPDATE tm_area SET area_nom = ?, area_descrip = ?, area_padre_id = ?, area_cargo_id = ?,
                             area_estado = ?, area_por_sede = ?, area_atiende = ?
                      WHERE area_id = ?")->execute($valores);
        return (int)$d["area_id"];
    }

    /* Baja lógica (no se borra: hay historial ligado) */
    public function desactivar_area($area_id){
        $this->Conexion()->prepare("UPDATE tm_area SET est = 0 WHERE area_id = ?")->execute(array((int)$area_id));
    }

    /* ---------- Pertenencias ---------- */
    public function miembros_area($area_id){
        return $this->todos("
            SELECT au.au_id, au.usu_id, u.usu_nom, u.usu_ape, s.sede_nom, f.fun_nom, au.fun_id, au.au_puesto
            FROM td_area_usuario au
            INNER JOIN tm_usuario u ON u.usu_id = au.usu_id AND u.est = 1
            INNER JOIN tm_sede s ON s.sede_id = au.sede_id
            INNER JOIN tm_funcion f ON f.fun_id = au.fun_id
            WHERE au.area_id = ? AND au.est = 1
            ORDER BY s.sede_id, au.fun_id, u.usu_nom", array((int)$area_id));
    }

    public function areas_usuario($usu_id){
        return $this->todos("
            SELECT au.au_id, au.area_id, a.area_nom, au.sede_id, s.sede_nom, au.fun_id, f.fun_nom, au.au_puesto
            FROM td_area_usuario au
            INNER JOIN tm_area a ON a.area_id = au.area_id AND a.est = 1
            INNER JOIN tm_sede s ON s.sede_id = au.sede_id
            INNER JOIN tm_funcion f ON f.fun_id = au.fun_id
            WHERE au.usu_id = ? AND au.est = 1
            ORDER BY au.fun_id, a.area_nom", array((int)$usu_id));
    }

    /* Agrega o reactiva una pertenencia (misma persona+área+sede = se actualiza) */
    public function guardar_membresia($usu_id, $area_id, $sede_id, $fun_id, $puesto){
        $this->Conexion()->prepare("
            INSERT INTO td_area_usuario (usu_id, area_id, sede_id, fun_id, au_puesto, est)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE fun_id = VALUES(fun_id), au_puesto = VALUES(au_puesto), est = 1")
            ->execute(array((int)$usu_id, (int)$area_id, (int)$sede_id, (int)$fun_id, $puesto));
    }

    public function quitar_membresia($au_id){
        $this->Conexion()->prepare("UPDATE td_area_usuario SET est = 0 WHERE au_id = ?")->execute(array((int)$au_id));
    }

    /* Texto corto para mostrar: "Contadora general · Contabilidad" */
    public function resumen_usuario($usu_id){
        $a = $this->areas_usuario($usu_id);
        if (!$a) { return ""; }
        return trim(($a[0]["au_puesto"] ? $a[0]["au_puesto"]." · " : "").$a[0]["area_nom"]);
    }
}
