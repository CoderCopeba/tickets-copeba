-- =====================================================================
-- Migración 2026-11-24 (2): bitácora del ticket
--  - Tipos de entrada: respuesta (visible al usuario), nota (interna TI),
--    evento (asignado, pospuesto, cerrado, reabierto...)
--  - Adjuntos ligados a una respuesta concreta
--  - Tiempo objetivo de atención (SLA) por prioridad
--  - Nuevo estado de ticket: 'En espera' (pospuesto)
-- Aplicar una sola vez, como root, con bd_copeba_tickets seleccionada.
-- Requiere haber aplicado antes 2026-11-24_prioridad.sql
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `td_ticketdetalle`
  ADD COLUMN `tickd_tipo` varchar(10) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'respuesta' AFTER `usu_id`,
  ADD KEY `ix_td_ticketdetalle_ticket` (`tick_id`, `fech_crea`);

-- Los mensajes automáticos antiguos pasan a ser eventos
UPDATE `td_ticketdetalle` SET `tickd_tipo` = 'evento'
 WHERE `tickd_descrip` IN ('Ticket Cerrado...', 'Ticket Re-Abierto...');

ALTER TABLE `td_documento`
  ADD COLUMN `tickd_id` int NULL AFTER `tick_id`,
  ADD KEY `ix_td_documento_ticket` (`tick_id`);

ALTER TABLE `tm_prioridad`
  ADD COLUMN `prio_sla_horas` int NOT NULL DEFAULT 24 AFTER `prio_descrip`;

UPDATE `tm_prioridad` SET `prio_sla_horas` = CASE `prio_id`
    WHEN 1 THEN 72   -- Baja: 3 días
    WHEN 2 THEN 24   -- Normal: 1 día
    WHEN 3 THEN 8    -- Alta: 8 horas
    WHEN 4 THEN 4    -- Crítica: 4 horas
    ELSE 24 END;

-- Índices para las listas y el Inicio
ALTER TABLE `tm_ticket`
  ADD KEY `ix_tm_ticket_estado` (`tick_estado`, `usu_asig`),
  ADD KEY `ix_tm_ticket_usuario` (`usu_id`);

-- Textos de eventos antiguos -> formato nuevo con el nombre de quien lo hizo
UPDATE `td_ticketdetalle` d JOIN `tm_usuario` u ON u.usu_id = d.usu_id
   SET d.tickd_descrip = CONCAT('Ticket cerrado por ', TRIM(CONCAT(u.usu_nom, ' ', u.usu_ape)))
 WHERE d.tickd_tipo = 'evento' AND d.tickd_descrip = 'Ticket Cerrado...';
UPDATE `td_ticketdetalle` d JOIN `tm_usuario` u ON u.usu_id = d.usu_id
   SET d.tickd_descrip = CONCAT('Ticket reabierto por ', TRIM(CONCAT(u.usu_nom, ' ', u.usu_ape)))
 WHERE d.tickd_tipo = 'evento' AND d.tickd_descrip = 'Ticket Re-Abierto...';
