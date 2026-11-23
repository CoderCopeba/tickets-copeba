<?php
    class Documento extends Conectar{
        public function insert_documento($tick_id,$doc_nom){
            $conectar= parent::conexion();
            /* consulta sql */
            $sql="INSERT INTO td_documento (doc_id,tick_id,doc_nom,fech_crea,est) VALUES (null,?,?,now(),1);";
            $sql = $conectar->prepare($sql);
            $sql->bindParam(1,$tick_id);
            $sql->bindParam(2,$doc_nom);
            $sql->execute();
        }

        /* Un documento por su id, o null */
        public function get_documento_x_id($doc_id){
            $conectar= parent::conexion();
            $sql="SELECT doc_id, tick_id, doc_nom FROM td_documento WHERE doc_id=? AND est=1";
            $sql = $conectar->prepare($sql);
            $sql->bindValue(1, (int)$doc_id, PDO::PARAM_INT);
            $sql->execute();
            $fila = $sql->fetch(PDO::FETCH_ASSOC);
            return is_array($fila) ? $fila : null;
        }

        public function get_documento_x_ticket($tick_id){
            $conectar= parent::conexion();
            /* consulta sql */
            $sql="SELECT * FROM td_documento WHERE tick_id=?";
            $sql = $conectar->prepare($sql);
            $sql->bindParam(1,$tick_id);
            $sql->execute();
            return $resultado = $sql->fetchAll(pdo::FETCH_ASSOC);
        }
    }
?>