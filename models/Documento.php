<?php
    /* Adjuntos de los tickets. tickd_id = respuesta/nota a la que pertenece
       (NULL = adjuntado al crear el ticket). */
    class Documento extends Conectar{

        public function insert_documento($tick_id, $doc_nom, $tickd_id = null){
            $sql = parent::conexion()->prepare("INSERT INTO td_documento (tick_id, tickd_id, doc_nom, fech_crea, est) VALUES (?, ?, ?, now(), 1)");
            $sql->execute(array((int)$tick_id, $tickd_id === null ? null : (int)$tickd_id, $doc_nom));
        }

        /* Un documento por su id (con el tipo de la entrada a la que pertenece), o null */
        public function get_documento_x_id($doc_id){
            $sql = parent::conexion()->prepare("
                SELECT d.doc_id, d.tick_id, d.tickd_id, d.doc_nom, COALESCE(td.tickd_tipo, 'respuesta') AS tipo
                FROM td_documento d
                LEFT JOIN td_ticketdetalle td ON td.tickd_id = d.tickd_id
                WHERE d.doc_id = ? AND d.est = 1");
            $sql->execute(array((int)$doc_id));
            $fila = $sql->fetch(PDO::FETCH_ASSOC);
            return is_array($fila) ? $fila : null;
        }

        /* Adjuntos de un ticket; los de notas internas solo si $con_notas */
        public function get_documento_x_ticket($tick_id, $con_notas = true){
            $sql = parent::conexion()->prepare("
                SELECT d.doc_id, d.tickd_id, d.doc_nom
                FROM td_documento d
                LEFT JOIN td_ticketdetalle td ON td.tickd_id = d.tickd_id
                WHERE d.tick_id = ? AND d.est = 1".($con_notas ? "" : " AND COALESCE(td.tickd_tipo, 'respuesta') <> 'nota'")."
                ORDER BY d.doc_id");
            $sql->execute(array((int)$tick_id));
            return $sql->fetchAll(PDO::FETCH_ASSOC);
        }
    }
?>
