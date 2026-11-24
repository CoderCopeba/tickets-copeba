-- Esquema de COPEBA TICKETS (estructura + catálogos; sin usuarios ni tickets)
-- Actualizado: 2026-11-25 (prioridades con SLA, bitácora, sedes y áreas, contraseñas temporales)
-- Importar en una base vacía creada con utf8mb4 / utf8mb4_spanish_ci.
-- Procedimientos con SQL SECURITY INVOKER y sin DEFINER.
-- Tras importar: crear la primera cuenta Administrador con  php instalar/crear_admin.php

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `td_area_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `td_area_usuario` (
  `au_id` int NOT NULL AUTO_INCREMENT,
  `usu_id` int NOT NULL,
  `area_id` int NOT NULL,
  `sede_id` int NOT NULL,
  `fun_id` int NOT NULL DEFAULT '3',
  `au_puesto` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `fech_crea` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `est` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`au_id`),
  UNIQUE KEY `uq_area_usuario` (`usu_id`,`area_id`,`sede_id`),
  KEY `ix_area_usuario_area` (`area_id`,`sede_id`),
  KEY `fk_au_sede` (`sede_id`),
  KEY `fk_au_funcion` (`fun_id`),
  CONSTRAINT `fk_au_area` FOREIGN KEY (`area_id`) REFERENCES `tm_area` (`area_id`),
  CONSTRAINT `fk_au_funcion` FOREIGN KEY (`fun_id`) REFERENCES `tm_funcion` (`fun_id`),
  CONSTRAINT `fk_au_sede` FOREIGN KEY (`sede_id`) REFERENCES `tm_sede` (`sede_id`),
  CONSTRAINT `fk_au_usuario` FOREIGN KEY (`usu_id`) REFERENCES `tm_usuario` (`usu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `td_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `td_ticketdetalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `td_ticketdetalle` (
  `tickd_id` int NOT NULL AUTO_INCREMENT,
  `tick_id` int NOT NULL,
  `usu_id` int NOT NULL,
  `tickd_tipo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'respuesta',
  `tickd_descrip` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `fech_crea` datetime NOT NULL,
  `est` int NOT NULL,
  PRIMARY KEY (`tickd_id`),
  KEY `ix_td_ticketdetalle_ticket` (`tick_id`,`fech_crea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `td_usuario_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `td_usuario_evento` (
  `ue_id` int NOT NULL AUTO_INCREMENT,
  `usu_id` int NOT NULL,
  `actor_id` int DEFAULT NULL,
  `ue_evento` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `ue_detalle` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `fech_crea` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ue_id`),
  KEY `ix_usuario_evento` (`usu_id`,`fech_crea`),
  KEY `fk_ue_actor` (`actor_id`),
  CONSTRAINT `fk_ue_actor` FOREIGN KEY (`actor_id`) REFERENCES `tm_usuario` (`usu_id`),
  CONSTRAINT `fk_ue_usuario` FOREIGN KEY (`usu_id`) REFERENCES `tm_usuario` (`usu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Historial de cuentas: temporal generada, contraseña cambiada, etc.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tm_area`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tm_area` (
  `area_id` int NOT NULL AUTO_INCREMENT,
  `area_nom` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `area_descrip` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `area_padre_id` int DEFAULT NULL,
  `area_cargo_id` int DEFAULT NULL,
  `area_estado` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'activa',
  `area_por_sede` tinyint NOT NULL DEFAULT '0',
  `area_atiende` tinyint NOT NULL DEFAULT '0',
  `est` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`area_id`),
  UNIQUE KEY `uq_tm_area_nom` (`area_nom`),
  KEY `fk_tm_area_padre` (`area_padre_id`),
  KEY `fk_tm_area_cargo` (`area_cargo_id`),
  CONSTRAINT `fk_tm_area_cargo` FOREIGN KEY (`area_cargo_id`) REFERENCES `tm_area` (`area_id`),
  CONSTRAINT `fk_tm_area_padre` FOREIGN KEY (`area_padre_id`) REFERENCES `tm_area` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='area_por_sede=1: existe en cada agencia; area_atiende=1: recibe tickets';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tm_categoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tm_categoria` (
  `cat_id` int NOT NULL AUTO_INCREMENT,
  `cat_nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `est` int NOT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tm_funcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tm_funcion` (
  `fun_id` int NOT NULL,
  `fun_nom` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `fun_descrip` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`fun_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tm_prioridad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tm_prioridad` (
  `prio_id` int NOT NULL,
  `prio_nom` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `prio_descrip` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `prio_sla_horas` int NOT NULL DEFAULT '24',
  `est` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`prio_id`),
  UNIQUE KEY `uq_tm_prioridad_nom` (`prio_nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tm_rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tm_rol` (
  `rol_id` int NOT NULL,
  `rol_nom` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`rol_id`),
  UNIQUE KEY `uq_tm_rol_nom` (`rol_nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tm_sede`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tm_sede` (
  `sede_id` int NOT NULL AUTO_INCREMENT,
  `sede_nom` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `sede_tipo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'agencia',
  `sede_municipio` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `sede_departamento` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `est` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`sede_id`),
  UNIQUE KEY `uq_tm_sede_nom` (`sede_nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tm_ticket`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tm_ticket` (
  `tick_id` int NOT NULL AUTO_INCREMENT,
  `usu_id` int NOT NULL,
  `cat_id` int NOT NULL,
  `prio_id` int NOT NULL DEFAULT '2',
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tm_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tm_usuario` (
  `usu_id` int NOT NULL AUTO_INCREMENT,
  `usu_nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `usu_ape` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `usu_usuario` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `usu_correo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `usu_pass` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `usu_pass_temporal` tinyint NOT NULL DEFAULT '0',
  `usu_pass_expira` datetime DEFAULT NULL,
  `fech_pass` datetime DEFAULT NULL,
  `rol_id` int NOT NULL,
  `sede_id` int DEFAULT NULL,
  `fech_crea` datetime DEFAULT NULL,
  `fech_modi` datetime DEFAULT NULL,
  `fech_elim` datetime DEFAULT NULL,
  `est` int NOT NULL,
  PRIMARY KEY (`usu_id`),
  UNIQUE KEY `uq_tm_usuario_usu_usuario` (`usu_usuario`),
  KEY `fk_tm_usuario_rol` (`rol_id`),
  KEY `fk_tm_usuario_sede` (`sede_id`),
  CONSTRAINT `fk_tm_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `tm_rol` (`rol_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_tm_usuario_sede` FOREIGN KEY (`sede_id`) REFERENCES `tm_sede` (`sede_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Tabla Mantenedor de Usuarios';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 DROP PROCEDURE IF EXISTS `sp_d_usuario_01` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `sp_d_usuario_01`(IN xusu_id INT)
    SQL SECURITY INVOKER
BEGIN
	UPDATE tm_usuario SET est='0', fech_elim = now() WHERE usu_id=xusu_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_i_ticketdetalle_01` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `sp_i_ticketdetalle_01`(IN xtick_id INT, IN xusu_id INT)
    SQL SECURITY INVOKER
BEGIN
	INSERT INTO td_ticketdetalle (tickd_id,tick_id,usu_id,tickd_descrip,fech_crea,est)
	VALUES (NULL,xtick_id,xusu_id,'Ticket Cerrado...',now(),'1');
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_l_usuario_01` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `sp_l_usuario_01`()
    SQL SECURITY INVOKER
BEGIN
	SELECT * FROM tm_usuario WHERE est='1';
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_l_usuario_02` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `sp_l_usuario_02`(IN xusu_id INT)
    SQL SECURITY INVOKER
BEGIN
	SELECT * FROM tm_usuario WHERE usu_id=xusu_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- Catálogos
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

INSERT INTO `tm_rol` (`rol_id`, `rol_nom`) VALUES (3,'Administrador del sistema');
INSERT INTO `tm_rol` (`rol_id`, `rol_nom`) VALUES (1,'Colaborador');

INSERT INTO `tm_categoria` (`cat_id`, `cat_nom`, `est`) VALUES (1,'Hardware',1);
INSERT INTO `tm_categoria` (`cat_id`, `cat_nom`, `est`) VALUES (2,'Software',1);
INSERT INTO `tm_categoria` (`cat_id`, `cat_nom`, `est`) VALUES (3,'Incidencia',1);
INSERT INTO `tm_categoria` (`cat_id`, `cat_nom`, `est`) VALUES (4,'Petición de Servicio',1);

INSERT INTO `tm_prioridad` (`prio_id`, `prio_nom`, `prio_descrip`, `prio_sla_horas`, `est`) VALUES (1,'Baja','Consulta o mejora; puede esperar',72,1);
INSERT INTO `tm_prioridad` (`prio_id`, `prio_nom`, `prio_descrip`, `prio_sla_horas`, `est`) VALUES (2,'Normal','No bloquea el trabajo',24,1);
INSERT INTO `tm_prioridad` (`prio_id`, `prio_nom`, `prio_descrip`, `prio_sla_horas`, `est`) VALUES (3,'Alta','Dificulta el trabajo de una persona o área',8,1);
INSERT INTO `tm_prioridad` (`prio_id`, `prio_nom`, `prio_descrip`, `prio_sla_horas`, `est`) VALUES (4,'Crítica','Detiene la atención a asociados o a toda una agencia',4,1);

INSERT INTO `tm_funcion` (`fun_id`, `fun_nom`, `fun_descrip`) VALUES (1,'Responsable','Encargado(a) del área o jefe(a) de agencia');
INSERT INTO `tm_funcion` (`fun_id`, `fun_nom`, `fun_descrip`) VALUES (2,'Suplente','Cubre al responsable en su ausencia');
INSERT INTO `tm_funcion` (`fun_id`, `fun_nom`, `fun_descrip`) VALUES (3,'Colaborador','Integrante del área (auxiliar, asesor, cajero, etc.)');
INSERT INTO `tm_funcion` (`fun_id`, `fun_nom`, `fun_descrip`) VALUES (4,'Pasante','Practicante temporal');

INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (1,'Sede Central Barraneché','central','Aldea Barraneché, Totonicapán','Totonicapán',1);
INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (2,'Agencia Totonicapán Central','agencia','Totonicapán','Totonicapán',1);
INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (3,'Agencia La Esperanza','agencia',NULL,NULL,1);
INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (4,'Agencia La Concordia','agencia',NULL,NULL,1);
INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (5,'Agencia Chimente','agencia',NULL,'Totonicapán',1);
INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (6,'Agencia San Rafael Pie de la Cuesta','agencia','San Rafael Pie de la Cuesta','San Marcos',1);
INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (7,'Agencia Soloma','agencia','San Pedro Soloma','Huehuetenango',1);
INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (8,'Agencia San Juan Ixcoy','agencia','San Juan Ixcoy','Huehuetenango',1);
INSERT INTO `tm_sede` (`sede_id`, `sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`, `est`) VALUES (9,'Supermercado Comunitario','supermercado',NULL,NULL,1);

INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (1,'Gerencia y Administración','Gerente general (dirige la parte administrativa), directivos y secretaría de administración',NULL,NULL,'activa',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (2,'Consejo de Administración','Órgano de gobierno de la cooperativa',NULL,NULL,'activa',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (3,'Cumplimiento','Oficial de cumplimiento, suplente y auxiliar',NULL,NULL,'activa',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (4,'Contabilidad','Contador(a) general, auxiliares y pasantes',NULL,NULL,'activa',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (5,'Jefatura de Agencia','Jefe(a) de cada agencia, incluida la de la sede central: Caja, Atención y Créditos',NULL,NULL,'activa',1,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (6,'Caja','Receptores pagadores',NULL,NULL,'activa',1,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (7,'Atención al Asociado','Secretaría y servicio al asociado',NULL,NULL,'activa',1,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (8,'Créditos','Asesores, oficial y administración de créditos',NULL,NULL,'activa',1,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (9,'Mercadeo','Promoción, imagen y eventos',NULL,NULL,'activa',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (10,'Informática','Soporte técnico, sistemas y equipos de cómputo',NULL,NULL,'activa',0,1,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (11,'Seguridad','Seguridad de instalaciones y valores',NULL,NULL,'activa',1,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (12,'Diseño','Diseño gráfico y material publicitario',9,NULL,'activa',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (13,'Bodega','Insumos, repuestos y almacén',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (14,'Compras','Adquisiciones y proveedores',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (15,'Recursos Humanos','Personal, contratación y capacitación',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (16,'Archivo','Resguardo de expedientes y documentos',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (17,'Auditoría Interna','Revisión y control interno',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (18,'Unidad de Riesgos','Gestión integral de riesgos',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (19,'Cobros','Recuperación de cartera',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (20,'Tesorería','Liquidez y manejo de fondos',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (21,'Asesoría Jurídica','Contratos, garantías y temas legales',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (22,'Servicios Generales','Mantenimiento, limpieza y vehículos',NULL,1,'planificada',0,0,1);
INSERT INTO `tm_area` (`area_id`, `area_nom`, `area_descrip`, `area_padre_id`, `area_cargo_id`, `area_estado`, `area_por_sede`, `area_atiende`, `est`) VALUES (23,'Comisión de Vigilancia','Órgano de control de la cooperativa',NULL,1,'planificada',0,0,1);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


SET FOREIGN_KEY_CHECKS = 1;
