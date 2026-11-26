-- =====================================================================
-- Migración 2026-11-25: Tareas por área (como las tareas de un curso en Canvas)
--  - El Responsable o Suplente de un área asigna una tarea a una, varias o
--    todas las personas de su área (en su sede si el área existe por sede).
--  - tm_tarea: la tarea (una sola, con instrucciones, urgencia y fecha límite).
--  - td_tarea_usuario: el avance de CADA persona asignada:
--      pendiente -> proceso -> entregada -> revisada
--                                       \-> devuelta (vuelve a trabajarla)
-- Solo crea tablas nuevas: no modifica ni borra nada existente.
-- Aplicar UNA sola vez en la nube (cuenta .root), con bd_copeba_tickets seleccionada.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE `tm_tarea` (
  `tarea_id` int NOT NULL AUTO_INCREMENT,
  `area_id` int NOT NULL,
  `sede_id` int DEFAULT NULL COMMENT 'Sede de quien asigna si el área existe por sede; NULL = toda el área',
  `usu_crea` int NOT NULL,
  `prio_id` int NOT NULL DEFAULT '2',
  `tarea_titulo` varchar(200) COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `tarea_descrip` longtext COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `fech_limite` date DEFAULT NULL,
  `fech_crea` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `est` int NOT NULL DEFAULT '1' COMMENT '1 = vigente, 0 = anulada',
  PRIMARY KEY (`tarea_id`),
  KEY `ix_tm_tarea_area` (`area_id`, `est`),
  KEY `ix_tm_tarea_crea` (`usu_crea`),
  CONSTRAINT `fk_tm_tarea_area` FOREIGN KEY (`area_id`) REFERENCES `tm_area` (`area_id`),
  CONSTRAINT `fk_tm_tarea_sede` FOREIGN KEY (`sede_id`) REFERENCES `tm_sede` (`sede_id`),
  CONSTRAINT `fk_tm_tarea_usuario` FOREIGN KEY (`usu_crea`) REFERENCES `tm_usuario` (`usu_id`),
  CONSTRAINT `fk_tm_tarea_prioridad` FOREIGN KEY (`prio_id`) REFERENCES `tm_prioridad` (`prio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `td_tarea_usuario` (
  `tu_id` int NOT NULL AUTO_INCREMENT,
  `tarea_id` int NOT NULL,
  `usu_id` int NOT NULL,
  `tu_estado` varchar(12) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pendiente',
  `tu_entrega` text COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Comentario de quien entrega',
  `tu_observacion` text COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Observación de quien revisa',
  `fech_entrega` datetime DEFAULT NULL,
  `fech_revision` datetime DEFAULT NULL,
  PRIMARY KEY (`tu_id`),
  UNIQUE KEY `uq_tarea_usuario` (`tarea_id`, `usu_id`),
  KEY `ix_td_tarea_usuario_usu` (`usu_id`, `tu_estado`),
  CONSTRAINT `fk_tu_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tm_tarea` (`tarea_id`),
  CONSTRAINT `fk_tu_usuario` FOREIGN KEY (`usu_id`) REFERENCES `tm_usuario` (`usu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
