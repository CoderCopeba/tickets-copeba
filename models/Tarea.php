<?php
/* Tareas por área (como las tareas de un curso en Canvas).
   El "curso" es el grupo: un área (y su sede, si el área existe por sede).
   Quién asigna: Responsable (fun_id 1) o Suplente (fun_id 2) del grupo, o el
   Administrador del sistema en cualquier área. Cada persona asignada lleva su
   propio avance en td_tarea_usuario. */
class Tarea extends Conectar {

    const FUN_RESPONSABLE = 1;
    const FUN_SUPLENTE    = 2;

    /* Avance de cada persona */
    const PENDIENTE = "pendiente";
    const PROCESO   = "proceso";
    const ENTREGADA = "entregada";
    const REVISADA  = "revisada";
    const DEVUELTA  = "devuelta";

    private function todos($sql, array $p = array()){
        $s = $this->Conexion()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fila($sql, array $p = array()){
        $s = $this->Conexion()->prepare($sql);
        $s->execute($p);
        $f = $s->fetch(PDO::FETCH_ASSOC);
        return $f === false ? null : $f;
    }

    private function ejecutar($sql, array $p = array()){
        $s = $this->Conexion()->prepare($sql);
        $s->execute($p);
        return $s->rowCount();
    }

    /* ---------- Grupos y personas ---------- */

    /* Grupos donde la persona puede asignar: [{area_id, area_nom, sede_id|null, sede_nom|null}]
       sede_id va solo si el área existe por sede (cada agencia tiene la suya). */
    public function grupos_asignables($usu_id, $es_admin){
        if ($es_admin){
            return $this->todos("
                SELECT a.area_id, a.area_nom, NULL AS sede_id, NULL AS sede_nom
                FROM tm_area a WHERE a.est = 1 ORDER BY a.area_nom");
        }
        return $this->todos("
            SELECT DISTINCT a.area_id, a.area_nom,
                   CASE WHEN a.area_por_sede = 1 THEN s.sede_id END AS sede_id,
                   CASE WHEN a.area_por_sede = 1 THEN s.sede_nom END AS sede_nom
            FROM td_area_usuario au
            INNER JOIN tm_area a ON a.area_id = au.area_id AND a.est = 1
            INNER JOIN tm_sede s ON s.sede_id = au.sede_id
            WHERE au.usu_id = ? AND au.est = 1 AND au.fun_id IN (?, ?)
            ORDER BY a.area_nom, sede_nom",
            array((int)$usu_id, self::FUN_RESPONSABLE, self::FUN_SUPLENTE));
    }

    /* ¿Está el grupo (área + sede) entre los que puede asignar? Normaliza sede_id. */
    public static function grupo_permitido(array $grupos, $area_id, $sede_id){
        foreach ($grupos as $g){
            if ((int)$g["area_id"] === (int)$area_id){
                if ($g["sede_id"] === null || (int)$g["sede_id"] === (int)$sede_id){
                    return array("area_id" => (int)$g["area_id"], "sede_id" => $g["sede_id"] === null ? null : (int)$g["sede_id"]);
                }
            }
        }
        return null;
    }

    /* La Jefatura de Agencia dirige a TODO el personal de su sede (Caja, Créditos...),
       no solo a quien esté en su misma área. */
    const AREA_JEFATURA_AGENCIA = "Jefatura de Agencia";

    /* Personas activas del grupo (sin quien asigna). Si el grupo es la Jefatura de
       Agencia de una sede: todas las personas de esa sede, de cualquier área. */
    public function personas_grupo($area_id, $sede_id, $excepto_usu_id){
        $sql = "
            SELECT au.usu_id, u.usu_nom, u.usu_ape,
                   MIN(f.fun_id) AS fun_id, MIN(f.fun_nom) AS fun_nom, MIN(a.area_nom) AS area_nom,
                   MIN(s.sede_nom) AS sede_nom, MIN(au.au_puesto) AS puesto
            FROM td_area_usuario au
            INNER JOIN tm_usuario u ON u.usu_id = au.usu_id AND u.est = 1
            INNER JOIN tm_area a ON a.area_id = au.area_id AND a.est = 1
            INNER JOIN tm_funcion f ON f.fun_id = au.fun_id
            INNER JOIN tm_sede s ON s.sede_id = au.sede_id
            WHERE au.est = 1 AND au.usu_id <> ?";
        $p = array((int)$excepto_usu_id);
        if ($sede_id !== null){
            $sql .= " AND au.sede_id = ?
                      AND (au.area_id = ? OR EXISTS(SELECT 1 FROM tm_area j WHERE j.area_id = ? AND j.area_nom = ?))";
            array_push($p, (int)$sede_id, (int)$area_id, (int)$area_id, self::AREA_JEFATURA_AGENCIA);
        } else {
            $sql .= " AND au.area_id = ?";
            $p[] = (int)$area_id;
        }
        $sql .= " GROUP BY au.usu_id, u.usu_nom, u.usu_ape ORDER BY u.usu_nom, u.usu_ape";
        return $this->todos($sql, $p);
    }

    /* ---------- Crear ---------- */

    /* Crea la tarea y una fila de avance por persona, todo o nada. Devuelve tarea_id. */
    public function crear(array $d, array $personas){
        $pdo = $this->Conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO tm_tarea (area_id, sede_id, usu_crea, prio_id, tarea_titulo, tarea_descrip, fech_limite)
                VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute(array($d["area_id"], $d["sede_id"], $d["usu_crea"], $d["prio_id"],
                                $d["titulo"], $d["descrip"], $d["fech_limite"]));
            $tarea_id = (int)$pdo->lastInsertId();
            /* Un solo INSERT con todas las personas (un viaje a la base) */
            $marcas = implode(", ", array_fill(0, count($personas), "(?, ?)"));
            $valores = array();
            foreach ($personas as $usu_id){ $valores[] = $tarea_id; $valores[] = (int)$usu_id; }
            $pdo->prepare("INSERT INTO td_tarea_usuario (tarea_id, usu_id) VALUES ".$marcas)->execute($valores);
            $pdo->commit();
            return $tarea_id;
        } catch (Exception $e){
            $pdo->rollBack();
            throw $e;
        }
    }

    /* ---------- Listas ---------- */

    /* Tareas asignadas a la persona, con su propio avance */
    public function mis_tareas($usu_id){
        return $this->todos("
            SELECT t.tarea_id, t.tarea_titulo, t.fech_limite, t.fech_crea, t.prio_id, p.prio_nom,
                   a.area_nom, CONCAT(c.usu_nom, ' ', c.usu_ape) AS asignada_por,
                   tu.tu_estado, tu.fech_entrega
            FROM td_tarea_usuario tu
            INNER JOIN tm_tarea t ON t.tarea_id = tu.tarea_id AND t.est = 1
            INNER JOIN tm_area a ON a.area_id = t.area_id
            INNER JOIN tm_usuario c ON c.usu_id = t.usu_crea
            INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
            WHERE tu.usu_id = ?
            ORDER BY t.fech_crea DESC", array((int)$usu_id));
    }

    /* Tareas de los grupos que la persona dirige (o todas si es administrador),
       con el conteo de avance: total, entregadas sin revisar, revisadas. */
    public function asignadas($usu_id, $es_admin){
        $sql = "
            SELECT t.tarea_id, t.tarea_titulo, t.fech_limite, t.fech_crea, t.prio_id, p.prio_nom,
                   a.area_nom, s.sede_nom, CONCAT(c.usu_nom, ' ', c.usu_ape) AS asignada_por,
                   COUNT(tu.tu_id) AS total,
                   SUM(CASE WHEN tu.tu_estado = 'entregada' THEN 1 ELSE 0 END) AS por_revisar,
                   SUM(CASE WHEN tu.tu_estado = 'revisada' THEN 1 ELSE 0 END) AS revisadas
            FROM tm_tarea t
            INNER JOIN tm_area a ON a.area_id = t.area_id
            LEFT JOIN tm_sede s ON s.sede_id = t.sede_id
            INNER JOIN tm_usuario c ON c.usu_id = t.usu_crea
            INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
            INNER JOIN td_tarea_usuario tu ON tu.tarea_id = t.tarea_id
            WHERE t.est = 1";
        $p = array();
        if (!$es_admin){
            $sql .= " AND (t.usu_crea = ? OR EXISTS(
                        SELECT 1 FROM td_area_usuario au
                        WHERE au.usu_id = ? AND au.est = 1 AND au.fun_id IN (?, ?)
                          AND au.area_id = t.area_id AND (t.sede_id IS NULL OR t.sede_id = au.sede_id)))";
            $p = array((int)$usu_id, (int)$usu_id, self::FUN_RESPONSABLE, self::FUN_SUPLENTE);
        }
        $sql .= " GROUP BY t.tarea_id, t.tarea_titulo, t.fech_limite, t.fech_crea, t.prio_id, p.prio_nom,
                           a.area_nom, s.sede_nom, c.usu_nom, c.usu_ape
                  ORDER BY t.fech_crea DESC";
        return $this->todos($sql, $p);
    }

    /* ---------- Detalle ---------- */

    public function tarea($tarea_id){
        return $this->fila("
            SELECT t.tarea_id, t.area_id, t.sede_id, t.usu_crea, t.prio_id, p.prio_nom,
                   t.tarea_titulo, t.tarea_descrip, t.fech_limite, t.fech_crea,
                   a.area_nom, s.sede_nom, CONCAT(c.usu_nom, ' ', c.usu_ape) AS asignada_por
            FROM tm_tarea t
            INNER JOIN tm_area a ON a.area_id = t.area_id
            LEFT JOIN tm_sede s ON s.sede_id = t.sede_id
            INNER JOIN tm_usuario c ON c.usu_id = t.usu_crea
            INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
            WHERE t.tarea_id = ? AND t.est = 1", array((int)$tarea_id));
    }

    /* Avance de todas las personas de la tarea */
    public function avances($tarea_id){
        return $this->todos("
            SELECT tu.usu_id, CONCAT(u.usu_nom, ' ', u.usu_ape) AS nombre, tu.tu_estado,
                   tu.tu_entrega, tu.tu_observacion, tu.fech_entrega, tu.fech_revision
            FROM td_tarea_usuario tu
            INNER JOIN tm_usuario u ON u.usu_id = tu.usu_id
            WHERE tu.tarea_id = ?
            ORDER BY u.usu_nom, u.usu_ape", array((int)$tarea_id));
    }

    /* ¿Puede dirigir la tarea? Administrador, quien la creó o Responsable/Suplente del grupo */
    public function puede_gestionar(array $tarea, $usu_id, $es_admin, array $grupos){
        return $es_admin
            || (int)$tarea["usu_crea"] === (int)$usu_id
            || self::grupo_permitido($grupos, $tarea["area_id"], $tarea["sede_id"]) !== null;
    }

    /* ---------- Cambios de avance ---------- */

    /* La persona asignada mueve su propio avance. Solo transiciones válidas:
       pendiente/devuelta -> proceso; pendiente/proceso/devuelta -> entregada. */
    public function avanzar($tarea_id, $usu_id, $nuevo, $comentario){
        if ($nuevo === self::PROCESO){
            return $this->ejecutar("
                UPDATE td_tarea_usuario SET tu_estado = 'proceso'
                WHERE tarea_id = ? AND usu_id = ? AND tu_estado IN ('pendiente', 'devuelta')",
                array((int)$tarea_id, (int)$usu_id)) > 0;
        }
        if ($nuevo === self::ENTREGADA){
            return $this->ejecutar("
                UPDATE td_tarea_usuario SET tu_estado = 'entregada', tu_entrega = ?, fech_entrega = NOW()
                WHERE tarea_id = ? AND usu_id = ? AND tu_estado IN ('pendiente', 'proceso', 'devuelta')",
                array($comentario, (int)$tarea_id, (int)$usu_id)) > 0;
        }
        return false;
    }

    /* Quien dirige revisa una entrega: revisada (terminada) o devuelta (con observación) */
    public function revisar($tarea_id, $usu_id, $decision, $observacion){
        if (!in_array($decision, array(self::REVISADA, self::DEVUELTA), true)){
            return false;
        }
        return $this->ejecutar("
            UPDATE td_tarea_usuario SET tu_estado = ?, tu_observacion = ?, fech_revision = NOW()
            WHERE tarea_id = ? AND usu_id = ? AND tu_estado = 'entregada'",
            array($decision, $observacion, (int)$tarea_id, (int)$usu_id)) > 0;
    }

    public function anular($tarea_id){
        return $this->ejecutar("UPDATE tm_tarea SET est = 0 WHERE tarea_id = ? AND est = 1", array((int)$tarea_id)) > 0;
    }
}
