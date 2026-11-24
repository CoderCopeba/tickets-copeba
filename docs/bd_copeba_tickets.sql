-- Esquema de TICKETS-COPEBA (solo estructura; sin datos de usuarios ni tickets)
-- Actualizado: 2026-11-24 (utf8mb4, categorías, prioridades con SLA, bitácora)
-- Importar en una base vacía. Procedimientos con SQL SECURITY INVOKER y sin DEFINER.
-- Tras importar: crear la primera cuenta Administrador con  php instalar/crear_admin.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `tm_usuario`;
DROP TABLE IF EXISTS `tm_ticket`;
DROP TABLE IF EXISTS `tm_categoria`;
DROP TABLE IF EXISTS `td_ticketdetalle`;
DROP TABLE IF EXISTS `td_documento`;
DROP TABLE IF EXISTS `tm_prioridad`;
DROP TABLE IF EXISTS `tm_rol`;

CREATE TABLE `tm_rol` (
  `rol_id` int NOT NULL,
  `rol_nom` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`rol_id`),
  UNIQUE KEY `uq_tm_rol_nom` (`rol_nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `tm_rol` (`rol_id`, `rol_nom`) VALUES
  (1,'Usuario'),
  (2,'Soporte'),
  (3,'Administrador');

CREATE TABLE `tm_prioridad` (
  `prio_id` int NOT NULL,
  `prio_nom` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `prio_descrip` varchar(80) COLLATE utf8mb4_spanish_ci NOT NULL,
  `prio_sla_horas` int NOT NULL DEFAULT 24,
  `est` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`prio_id`),
  UNIQUE KEY `uq_tm_prioridad_nom` (`prio_nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO `tm_prioridad` (`prio_id`, `prio_nom`, `prio_descrip`, `prio_sla_horas`, `est`) VALUES
  (1,'Baja','Consulta o mejora; puede esperar',72,1),
  (2,'Normal','No bloquea el trabajo',24,1),
  (3,'Alta','Dificulta el trabajo de una persona o área',8,1),
  (4,'Crítica','Detiene la atención a asociados o a toda una agencia',4,1);

CREATE TABLE `td_documento` (
  `doc_id` int NOT NULL AUTO_INCREMENT,
  `tick_id` int NOT NULL,
  `tickd_id` int DEFAULT NULL,
  `doc_nom` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `fech_crea` datetime NOT NULL,
  `est` int NOT NULL,
  PRIMARY KEY (`doc_id`),
  KEY `ix_td_documento_ticket` (`tick_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `td_ticketdetalle` (
  `tickd_id` int NOT NULL AUTO_INCREMENT,
  `tick_id` int NOT NULL,
  `usu_id` int NOT NULL,
  `tickd_tipo` varchar(10) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'respuesta',
  `tickd_descrip` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `fech_crea` datetime NOT NULL,
  `est` int NOT NULL,
  PRIMARY KEY (`tickd_id`),
  KEY `ix_td_ticketdetalle_ticket` (`tick_id`,`fech_crea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `tm_categoria` (
  `cat_id` int NOT NULL AUTO_INCREMENT,
  `cat_nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `est` int NOT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Categorías iniciales (se pueden ampliar después)
INSERT INTO `tm_categoria` (`cat_id`, `cat_nom`, `est`) VALUES
  (1,'Hardware',1),
  (2,'Software',1),
  (3,'Incidencia',1),
  (4,'Petición de Servicio',1);

CREATE TABLE `tm_ticket` (
  `tick_id` int NOT NULL AUTO_INCREMENT,
  `usu_id` int NOT NULL,
  `cat_id` int NOT NULL,
  `prio_id` int NOT NULL DEFAULT 2,
  `tick_titulo` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `tick_descrip` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `tick_estado` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `fech_crea` datetime DEFAULT NULL,
  `usu_asig` int DEFAULT NULL,
  `fech_asig` datetime DEFAULT NULL,
  `est` int NOT NULL,
  PRIMARY KEY (`tick_id`),
  KEY `fk_tm_ticket_prioridad` (`prio_id`),
  KEY `ix_tm_ticket_estado` (`tick_estado`,`usu_asig`),
  KEY `ix_tm_ticket_usuario` (`usu_id`),
  CONSTRAINT `fk_tm_ticket_prioridad` FOREIGN KEY (`prio_id`) REFERENCES `tm_prioridad` (`prio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE `tm_usuario` (
  `usu_id` int NOT NULL AUTO_INCREMENT,
  `usu_nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `usu_ape` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `usu_usuario` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `usu_correo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `usu_pass` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `rol_id` int NOT NULL,
  `fech_crea` datetime DEFAULT NULL,
  `fech_modi` datetime DEFAULT NULL,
  `fech_elim` datetime DEFAULT NULL,
  `est` int NOT NULL,
  PRIMARY KEY (`usu_id`),
  UNIQUE KEY `uq_tm_usuario_usu_usuario` (`usu_usuario`),
  KEY `fk_tm_usuario_rol` (`rol_id`),
  CONSTRAINT `fk_tm_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `tm_rol` (`rol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Tabla Mantenedor de Usuarios';

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_d_usuario_01`$$
CREATE PROCEDURE `sp_d_usuario_01`(IN `xusu_id` INT)
    SQL SECURITY INVOKER
BEGIN
	UPDATE tm_usuario 
	SET 
		est='0',
		fech_elim = now() 
	where usu_id=xusu_id;
END$$

DROP PROCEDURE IF EXISTS `sp_i_ticketdetalle_01`$$
CREATE PROCEDURE `sp_i_ticketdetalle_01`(IN `xtick_id` INT, IN `xusu_id` INT)
    SQL SECURITY INVOKER
BEGIN
	INSERT INTO td_ticketdetalle 
    (tickd_id,tick_id,usu_id,tickd_descrip,fech_crea,est) 
    VALUES 
    (NULL,xtick_id,xusu_id,'Ticket Cerrado...',now(),'1');
END$$

DROP PROCEDURE IF EXISTS `sp_l_usuario_01`$$
CREATE PROCEDURE `sp_l_usuario_01`()
    SQL SECURITY INVOKER
BEGIN
	SELECT * FROM tm_usuario where est='1';
END$$

DROP PROCEDURE IF EXISTS `sp_l_usuario_02`$$
CREATE PROCEDURE `sp_l_usuario_02`(IN `xusu_id` INT)
    SQL SECURITY INVOKER
BEGIN
	SELECT * FROM tm_usuario where usu_id=xusu_id;
END$$

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;
