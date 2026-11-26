<?php
/* Planificación semanal y mensual.
   Cada persona escribe su plan del periodo y lo entrega antes de que empiece
   (el día anterior; después queda marcado "tarde"). Durante el periodo marca cada
   actividad: hecho, no cumplido o pospuesto (pasa sola al plan de la nueva fecha).
   La jefatura del área (Responsable/Suplente, misma regla que Tareas) revisa y comenta. */
require_once(__DIR__."/Tarea.php");

class Plan extends Conectar {

    const SEMANA = "semana";
    const MES    = "mes";

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

    /* ---------- Periodos ---------- */

    /* Periodo que contiene la fecha: semana de lunes a domingo, o mes completo.
       Devuelve null si el tipo o la fecha no son válidos. */
    public static function periodo($tipo, $fecha){
        $d = DateTime::createFromFormat("!Y-m-d", (string)$fecha);
        if (!$d || $d->format("Y-m-d") !== (string)$fecha || !in_array($tipo, array(self::SEMANA, self::MES), true)){
            return null;
        }
        if ($tipo === self::SEMANA){
            $inicio = (clone $d)->modify("-".((int)$d->format("N") - 1)." days");
            $fin = (clone $inicio)->modify("+6 days");
        } else {
            $inicio = (clone $d)->modify("first day of this month");
            $fin = (clone $d)->modify("last day of this month");
        }
        $plazo = (clone $inicio)->modify("-1 day");      /* se entrega hasta el día anterior */
        return array(
            "tipo"   => $tipo,
            "inicio" => $inicio->format("Y-m-d"),
            "fin"    => $fin->format("Y-m-d"),
            "plazo"  => $plazo->format("Y-m-d"),
        );
    }

    /* ¿Se entregó tarde? (después del día anterior al inicio) */
    public static function tarde($fech_entrega, $plazo){
        return $fech_entrega !== null && substr($fech_entrega, 0, 10) > $plazo;
    }

    /* ---------- Plan y actividades ---------- */

    public function plan_de($usu_id, array $p){
        return $this->fila("
            SELECT pl.*, CONCAT(r.usu_nom, ' ', r.usu_ape) AS revisado_por
            FROM tm_plan pl LEFT JOIN tm_usuario r ON r.usu_id = pl.usu_revisa
            WHERE pl.usu_id = ? AND pl.plan_tipo = ? AND pl.fech_inicio = ?",
            array((int)$usu_id, $p["tipo"], $p["inicio"]));
    }

    public function plan($plan_id){
        return $this->fila("
            SELECT pl.*, CONCAT(r.usu_nom, ' ', r.usu_ape) AS revisado_por,
                   CONCAT(u.usu_nom, ' ', u.usu_ape) AS persona
            FROM tm_plan pl
            INNER JOIN tm_usuario u ON u.usu_id = pl.usu_id
            LEFT JOIN tm_usuario r ON r.usu_id = pl.usu_revisa
            WHERE pl.plan_id = ?", array((int)$plan_id));
    }

    /* Plan del periodo; lo crea en borrador si todavía no existe. Devuelve plan_id. */
    public function asegurar_plan($usu_id, array $p){
        $existe = $this->plan_de($usu_id, $p);
        if ($existe !== null){ return (int)$existe["plan_id"]; }
        try {
            $this->ejecutar("INSERT INTO tm_plan (usu_id, plan_tipo, fech_inicio, fech_fin) VALUES (?, ?, ?, ?)",
                array((int)$usu_id, $p["tipo"], $p["inicio"], $p["fin"]));
            return (int)$this->Conexion()->lastInsertId();
        } catch (PDOException $e){
            /* dos pestañas a la vez: el otro ya lo creó */
            $existe = $this->plan_de($usu_id, $p);
            if ($existe === null){ throw $e; }
            return (int)$existe["plan_id"];
        }
    }

    public function actividades($plan_id){
        return $this->todos("
            SELECT a.act_id, a.act_texto, a.act_dia, a.act_estado, a.act_justificacion, a.act_extra,
                   a.act_origen_id, a.fech_cierre, o.act_justificacion AS origen_justificacion,
                   a.act_tick_id, t.tick_titulo, t.tick_estado
            FROM td_plan_actividad a
            LEFT JOIN td_plan_actividad o ON o.act_id = a.act_origen_id
            LEFT JOIN tm_ticket t ON t.tick_id = a.act_tick_id
            WHERE a.plan_id = ? AND a.est = 1
            ORDER BY a.act_dia IS NULL, a.act_dia, a.act_id", array((int)$plan_id));
    }

    /* Actividad vigente con los datos de su plan (para validar dueño y estado) */
    public function actividad($act_id){
        return $this->fila("
            SELECT a.*, pl.usu_id, pl.plan_tipo, pl.fech_inicio, pl.fech_fin, pl.plan_estado
            FROM td_plan_actividad a INNER JOIN tm_plan pl ON pl.plan_id = a.plan_id
            WHERE a.act_id = ? AND a.est = 1", array((int)$act_id));
    }

    public function agregar($plan_id, $texto, $dia, $extra, $origen_id = null, $tick_id = null){
        $this->ejecutar("
            INSERT INTO td_plan_actividad (plan_id, act_texto, act_dia, act_extra, act_origen_id, act_tick_id) VALUES (?, ?, ?, ?, ?, ?)",
            array((int)$plan_id, $texto, $dia, $extra ? 1 : 0, $origen_id, $tick_id === null ? null : (int)$tick_id));
        return (int)$this->Conexion()->lastInsertId();
    }

    public function editar($act_id, $texto, $dia){
        return $this->ejecutar("UPDATE td_plan_actividad SET act_texto = ?, act_dia = ? WHERE act_id = ? AND est = 1",
            array($texto, $dia, (int)$act_id));
    }

    /* La aplicación no borra filas: la actividad se desactiva */
    public function quitar($act_id){
        return $this->ejecutar("UPDATE td_plan_actividad SET est = 0 WHERE act_id = ?", array((int)$act_id));
    }

    public function entregar($plan_id){
        return $this->ejecutar("
            UPDATE tm_plan SET plan_estado = 'entregado', fech_entrega = NOW()
            WHERE plan_id = ? AND plan_estado = 'borrador'", array((int)$plan_id)) > 0;
    }

    /* Hecho, no cumplido o pospuesto (el texto de justificación lo valida el controlador).
       Se puede corregir mientras el plan no esté revisado. */
    public function marcar($act_id, $estado, $justificacion){
        return $this->ejecutar("
            UPDATE td_plan_actividad SET act_estado = ?, act_justificacion = ?, fech_cierre = IF(? = 'pendiente', NULL, NOW())
            WHERE act_id = ? AND est = 1",
            array($estado, $justificacion, $estado, (int)$act_id)) > 0;
    }

    /* Pospone: marca la actividad y la copia al plan del periodo de la nueva fecha
       (del mismo tipo), creándolo en borrador si no existe. Todo o nada. */
    public function posponer(array $act, $justificacion, $nueva_fecha){
        $destino = self::periodo($act["plan_tipo"], $nueva_fecha);
        $pdo = $this->Conexion();
        $pdo->beginTransaction();
        try {
            $this->marcar($act["act_id"], "pospuesto", $justificacion);
            $plan_destino = $this->asegurar_plan($act["usu_id"], $destino);
            $estado_destino = $this->fila("SELECT plan_estado FROM tm_plan WHERE plan_id = ?", array($plan_destino));
            $this->agregar($plan_destino, $act["act_texto"], $nueva_fecha,
                $estado_destino["plan_estado"] !== "borrador", (int)$act["act_id"], $act["act_tick_id"]);
            $pdo->commit();
            return $destino;
        } catch (Exception $e){
            $pdo->rollBack();
            throw $e;
        }
    }

    public function revisar($plan_id, $revisor_id, $comentario){
        return $this->ejecutar("
            UPDATE tm_plan SET plan_estado = 'revisado', plan_comentario = ?, usu_revisa = ?, fech_revision = NOW()
            WHERE plan_id = ? AND plan_estado IN ('entregado', 'revisado')",
            array($comentario, (int)$revisor_id, (int)$plan_id)) > 0;
    }

    /* ---------- Tickets en el plan (quien atiende tickets) ---------- */

    /* Tickets abiertos que puede planificar: los asignados a ella y los que no tienen técnico */
    public function tickets_disponibles($usu_id){
        return $this->todos("
            SELECT t.tick_id, t.tick_titulo, t.tick_estado, t.usu_asig, t.fech_crea, t.prio_id, p.prio_nom,
                   CONCAT(u.usu_nom, ' ', u.usu_ape) AS solicitante, s.sede_nom
            FROM tm_ticket t
            INNER JOIN tm_usuario u ON u.usu_id = t.usu_id
            INNER JOIN tm_prioridad p ON p.prio_id = t.prio_id
            LEFT JOIN tm_sede s ON s.sede_id = t.sede_id
            WHERE t.est = 1 AND t.tick_estado <> 'Cerrado' AND (t.usu_asig IS NULL OR t.usu_asig = ?)
            ORDER BY t.prio_id DESC, t.fech_crea", array((int)$usu_id));
    }

    /* El ticket, si sigue abierto y es suyo o no tiene técnico; si no, null */
    public function ticket_planificable($tick_id, $usu_id){
        return $this->fila("
            SELECT tick_id, tick_titulo, usu_asig FROM tm_ticket
            WHERE tick_id = ? AND est = 1 AND tick_estado <> 'Cerrado' AND (usu_asig IS NULL OR usu_asig = ?)",
            array((int)$tick_id, (int)$usu_id));
    }

    /* ---------- Jefatura ---------- */

    /* Personas que dirige (las de sus grupos, misma regla que Tareas), sin repetir */
    public function personas_a_cargo($usu_id, $es_admin){
        if ($es_admin){
            /* Administrador: todas las cuentas activas en una sola consulta */
            return array_map(function ($x){
                $x["usu_id"] = (int)$x["usu_id"];
                return $x;
            }, $this->todos("
                SELECT u.usu_id, CONCAT(u.usu_nom, ' ', u.usu_ape) AS nombre,
                       MIN(a.area_nom) AS area, MIN(s.sede_nom) AS sede, MIN(COALESCE(au.au_puesto, f.fun_nom)) AS puesto
                FROM tm_usuario u
                LEFT JOIN td_area_usuario au ON au.usu_id = u.usu_id AND au.est = 1
                LEFT JOIN tm_area a ON a.area_id = au.area_id
                LEFT JOIN tm_sede s ON s.sede_id = au.sede_id
                LEFT JOIN tm_funcion f ON f.fun_id = au.fun_id
                WHERE u.est = 1 AND u.usu_id <> ?
                GROUP BY u.usu_id, u.usu_nom, u.usu_ape
                ORDER BY u.usu_nom, u.usu_ape", array((int)$usu_id)));
        }
        $tarea = new Tarea();
        $personas = array();
        foreach ($tarea->grupos_asignables($usu_id, $es_admin) as $g){
            foreach ($tarea->personas_grupo($g["area_id"], $g["sede_id"], $usu_id) as $x){
                $id = (int)$x["usu_id"];
                if (!isset($personas[$id])){
                    $personas[$id] = array("usu_id" => $id, "nombre" => $x["usu_nom"]." ".$x["usu_ape"],
                                           "area" => $x["area_nom"], "sede" => $x["sede_nom"], "puesto" => $x["puesto"] ?: $x["fun_nom"]);
                }
            }
        }
        return array_values($personas);
    }

    /* Resumen de los planes del periodo para esas personas (una sola consulta) */
    public function resumen_equipo(array $usu_ids, array $p){
        if (!$usu_ids){ return array(); }
        $marcas = implode(",", array_fill(0, count($usu_ids), "?"));
        $filas = $this->todos("
            SELECT pl.usu_id, pl.plan_id, pl.plan_estado, pl.fech_entrega,
                   COUNT(a.act_id) AS total,
                   SUM(CASE WHEN a.act_estado = 'hecho' THEN 1 ELSE 0 END) AS hechas,
                   SUM(CASE WHEN a.act_estado = 'no_cumplido' THEN 1 ELSE 0 END) AS no_cumplidas,
                   SUM(CASE WHEN a.act_estado = 'pospuesto' THEN 1 ELSE 0 END) AS pospuestas
            FROM tm_plan pl
            LEFT JOIN td_plan_actividad a ON a.plan_id = pl.plan_id AND a.est = 1
            WHERE pl.plan_tipo = ? AND pl.fech_inicio = ? AND pl.usu_id IN (".$marcas.")
            GROUP BY pl.usu_id, pl.plan_id, pl.plan_estado, pl.fech_entrega",
            array_merge(array($p["tipo"], $p["inicio"]), array_map("intval", $usu_ids)));
        $por_usuario = array();
        foreach ($filas as $f){ $por_usuario[(int)$f["usu_id"]] = $f; }
        return $por_usuario;
    }

    /* ¿Puede supervisar el plan de esa persona? Administrador o jefatura de alguno de sus grupos */
    public function puede_supervisar($jefe_id, $es_admin, $usu_id){
        if ($es_admin){ return true; }
        foreach ($this->personas_a_cargo($jefe_id, false) as $x){
            if ($x["usu_id"] === (int)$usu_id){ return true; }
        }
        return false;
    }
}
