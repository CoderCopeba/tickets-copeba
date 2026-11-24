-- =====================================================================
-- Migración 2026-11-25 (3): compatibilidad con bases en la nube (TiDB Cloud)
--  - Cotejamiento utf8mb4_0900_ai_ci (TiDB no tiene utf8mb4_spanish_ci).
--    Igual que antes: ignora mayúsculas y acentos al comparar; guarda ñ y emojis.
--  - Se eliminan los 4 procedimientos almacenados (TiDB no los admite);
--    el código PHP ya usa consultas normales en su lugar.
-- Aplicar una sola vez, como root, con bd_copeba_tickets seleccionada.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER DATABASE `bd_copeba_tickets` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

ALTER TABLE `tm_rol`            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `tm_categoria`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `tm_prioridad`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `tm_funcion`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `tm_sede`           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `tm_area`           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `tm_usuario`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `td_area_usuario`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `td_usuario_evento` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `tm_ticket`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `td_ticketdetalle`  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
ALTER TABLE `td_documento`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

DROP PROCEDURE IF EXISTS `sp_d_usuario_01`;
DROP PROCEDURE IF EXISTS `sp_i_ticketdetalle_01`;
DROP PROCEDURE IF EXISTS `sp_l_usuario_01`;
DROP PROCEDURE IF EXISTS `sp_l_usuario_02`;

SET FOREIGN_KEY_CHECKS = 1;
