-- =====================================================================
-- Migración 2026-11-25: contraseñas temporales y control de cuentas
--  - usu_pass_temporal = 1: al entrar, la persona DEBE crear su contraseña
--  - usu_pass_expira: la temporal deja de servir pasada esta fecha
--  - fech_pass: último cambio de contraseña
--  - td_usuario_evento: historial (quién generó la temporal, cuándo se cambió)
-- Las contraseñas NUNCA se guardan legibles: solo su hash (password_hash).
-- Aplicar una sola vez, como root, con bd_copeba_tickets seleccionada.
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `tm_usuario`
  ADD COLUMN `usu_pass_temporal` tinyint NOT NULL DEFAULT 0 AFTER `usu_pass`,
  ADD COLUMN `usu_pass_expira` datetime DEFAULT NULL AFTER `usu_pass_temporal`,
  ADD COLUMN `fech_pass` datetime DEFAULT NULL AFTER `usu_pass_expira`;

CREATE TABLE IF NOT EXISTS `td_usuario_evento` (
  `ue_id` int NOT NULL AUTO_INCREMENT,
  `usu_id` int NOT NULL,
  `actor_id` int DEFAULT NULL,
  `ue_evento` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `ue_detalle` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `fech_crea` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ue_id`),
  KEY `ix_usuario_evento` (`usu_id`, `fech_crea`),
  CONSTRAINT `fk_ue_usuario` FOREIGN KEY (`usu_id`) REFERENCES `tm_usuario` (`usu_id`),
  CONSTRAINT `fk_ue_actor` FOREIGN KEY (`actor_id`) REFERENCES `tm_usuario` (`usu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci
  COMMENT='Historial de cuentas: temporal generada, contraseña cambiada, etc.';
