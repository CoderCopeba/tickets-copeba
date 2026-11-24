-- =====================================================================
-- Migración 2026-11-24: prioridad (urgencia) de los tickets
-- Aplicar una sola vez en bases creadas antes de esta fecha, como root,
-- con la base bd_copeba_tickets seleccionada.
-- Los tickets existentes quedan con prioridad "Normal".
-- No requiere permisos nuevos: tickets_app ya tiene SELECT/INSERT/UPDATE
-- sobre bd_copeba_tickets.*
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tm_prioridad` (
  `prio_id` int NOT NULL,
  `prio_nom` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `prio_descrip` varchar(80) COLLATE utf8mb4_spanish_ci NOT NULL,
  `est` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`prio_id`),
  UNIQUE KEY `uq_tm_prioridad_nom` (`prio_nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `tm_prioridad` (`prio_id`, `prio_nom`, `prio_descrip`, `est`) VALUES
  (1,'Baja','Consulta o mejora; puede esperar',1),
  (2,'Normal','No bloquea el trabajo',1),
  (3,'Alta','Dificulta el trabajo de una persona o área',1),
  (4,'Crítica','Detiene la atención a asociados o a toda una agencia',1)
ON DUPLICATE KEY UPDATE `prio_descrip` = VALUES(`prio_descrip`);

ALTER TABLE `tm_ticket`
  ADD COLUMN `prio_id` int NOT NULL DEFAULT 2 AFTER `cat_id`,
  ADD KEY `fk_tm_ticket_prioridad` (`prio_id`),
  ADD CONSTRAINT `fk_tm_ticket_prioridad` FOREIGN KEY (`prio_id`) REFERENCES `tm_prioridad` (`prio_id`);
