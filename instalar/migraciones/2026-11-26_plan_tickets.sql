-- =====================================================================
-- Migración 2026-11-26 (2): tickets dentro de la planificación
--  - Una actividad del plan puede venir de un ticket (p. ej. "Viaje a Soloma"
--    para el ticket de las cámaras). Si el ticket no tenía técnico, quien lo
--    planifica lo toma.
--  - Solo agrega una columna opcional a td_plan_actividad (creada el mismo día);
--    no modifica datos existentes.
-- Aplicar UNA sola vez en la nube (cuenta .root), después de 2026-11-26_planificacion.sql
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `td_plan_actividad`
  ADD COLUMN `act_tick_id` int DEFAULT NULL COMMENT 'Ticket del que viene la actividad' AFTER `act_origen_id`;

-- TiDB no admite crear la columna y su índice en la misma sentencia
ALTER TABLE `td_plan_actividad` ADD KEY `ix_td_plan_actividad_ticket` (`act_tick_id`);
