<?php
/* Prioridad (urgencia) de los tickets: 1 Baja, 2 Normal, 3 Alta, 4 Crítica */
class Prioridad extends Conectar {

    const NORMAL = 2;

    public function listar(){
        $stmt = $this->Conexion()->prepare("SELECT prio_id, prio_nom, prio_descrip FROM tm_prioridad WHERE est=1 ORDER BY prio_id");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existe($prio_id){
        $stmt = $this->Conexion()->prepare("SELECT COUNT(*) FROM tm_prioridad WHERE prio_id=? AND est=1");
        $stmt->execute(array((int)$prio_id));
        return (int)$stmt->fetchColumn() > 0;
    }
}
