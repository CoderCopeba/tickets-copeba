-- =====================================================================
-- Migración 2026-11-26: Planificación semanal y mensual
--  - Cada persona escribe su plan del periodo (semana lunes-domingo o mes) y lo
--    entrega antes de que empiece (el día anterior; después queda "tarde").
--  - Durante el periodo marca cada actividad: hecho, no cumplido (con
--    justificación) o pospuesto (con justificación y nueva fecha: la actividad
--    pasa sola al plan del periodo de esa fecha).
--  - La jefatura del área (Responsable/Suplente) revisa y comenta.
-- Solo crea tablas nuevas: no modifica ni borra nada existente.
-- Aplicar UNA sola vez en la nube (cuenta .root), con bd_copeba_tickets seleccionada.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE `tm_plan` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `usu_id` int NOT NULL,
  `plan_tipo` varchar(6) COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'semana | mes',
  `fech_inicio` date NOT NULL COMMENT 'Lunes de la semana o día 1 del mes',
  `fech_fin` date NOT NULL,
  `plan_estado` varchar(10) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'borrador' COMMENT 'borrador | entregado | revisado',
  `fech_entrega` datetime DEFAULT NULL,
  `plan_comentario` text COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Comentario de la jefatura',
  `usu_revisa` int DEFAULT NULL,
  `fech_revision` datetime DEFAULT NULL,
  `fech_crea` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `uq_plan_periodo` (`usu_id`, `plan_tipo`, `fech_inicio`),
  KEY `ix_tm_plan_periodo` (`plan_tipo`, `fech_inicio`),
  CONSTRAINT `fk_tm_plan_usuario` FOREIGN KEY (`usu_id`) REFERENCES `tm_usuario` (`usu_id`),
  CONSTRAINT `fk_tm_plan_revisa` FOREIGN KEY (`usu_revisa`) REFERENCES `tm_usuario` (`usu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `td_plan_actividad` (
  `act_id` int NOT NULL AUTO_INCREMENT,
  `plan_id` int NOT NULL,
  `act_texto` varchar(300) COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `act_dia` date DEFAULT NULL COMMENT 'Día previsto (opcional), dentro del periodo',
  `act_estado` varchar(12) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente | hecho | no_cumplido | pospuesto',
  `act_justificacion` text COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `act_extra` tinyint NOT NULL DEFAULT '0' COMMENT '1 = agregada después de entregar el plan',
  `act_origen_id` int DEFAULT NULL COMMENT 'Actividad pospuesta de la que viene',
  `fech_cierre` datetime DEFAULT NULL,
  `fech_crea` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `est` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`act_id`),
  KEY `ix_td_plan_actividad_plan` (`plan_id`, `est`),
  CONSTRAINT `fk_act_plan` FOREIGN KEY (`plan_id`) REFERENCES `tm_plan` (`plan_id`),
  CONSTRAINT `fk_act_origen` FOREIGN KEY (`act_origen_id`) REFERENCES `td_plan_actividad` (`act_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
