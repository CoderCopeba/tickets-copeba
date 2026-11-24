-- =====================================================================
-- Migración 2026-11-25 (2): de dónde sale cada ticket y cuándo se cerró
--  - area_id / sede_id: área y sede del solicitante AL CREAR el ticket
--    (una foto: si la persona cambia de área, sus tickets viejos no se mueven)
--  - fech_cierre: cuándo se cerró (para tiempos de resolución y SLA)
-- Los tickets existentes se completan con el área principal actual del
-- solicitante y con el último evento de cierre de su bitácora.
-- Aplicar una sola vez, como root, con bd_copeba_tickets seleccionada.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `tm_ticket`
  ADD COLUMN `area_id` int DEFAULT NULL AFTER `prio_id`,
  ADD COLUMN `sede_id` int DEFAULT NULL AFTER `area_id`,
  ADD COLUMN `fech_cierre` datetime DEFAULT NULL AFTER `fech_asig`,
  ADD KEY `ix_tm_ticket_area` (`area_id`),
  ADD KEY `ix_tm_ticket_sede` (`sede_id`),
  ADD KEY `ix_tm_ticket_fechas` (`fech_crea`, `fech_cierre`),
  ADD CONSTRAINT `fk_tm_ticket_area` FOREIGN KEY (`area_id`) REFERENCES `tm_area` (`area_id`),
  ADD CONSTRAINT `fk_tm_ticket_sede` FOREIGN KEY (`sede_id`) REFERENCES `tm_sede` (`sede_id`);

-- Área principal del solicitante: la de mayor responsabilidad (Responsable antes que Colaborador)
UPDATE `tm_ticket` t
   SET t.area_id = (SELECT au.area_id FROM td_area_usuario au
                     WHERE au.usu_id = t.usu_id AND au.est = 1
                     ORDER BY au.fun_id, au.au_id LIMIT 1),
       t.sede_id = COALESCE(
                     (SELECT au.sede_id FROM td_area_usuario au
                       WHERE au.usu_id = t.usu_id AND au.est = 1
                       ORDER BY au.fun_id, au.au_id LIMIT 1),
                     (SELECT u.sede_id FROM tm_usuario u WHERE u.usu_id = t.usu_id))
 WHERE t.area_id IS NULL;

-- Fecha de cierre de los tickets ya cerrados
UPDATE `tm_ticket` t
   SET t.fech_cierre = (SELECT MAX(d.fech_crea) FROM td_ticketdetalle d
                         WHERE d.tick_id = t.tick_id AND d.tickd_tipo = 'evento'
                           AND d.tickd_descrip LIKE 'Ticket cerrado%')
 WHERE t.tick_estado = 'Cerrado' AND t.fech_cierre IS NULL;
