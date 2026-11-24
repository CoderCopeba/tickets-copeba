-- =====================================================================
-- Migración 2026-11-24 (3): sedes, áreas, funciones y pertenencias
-- (modelo tipo Canvas: el rol de trabajo es POR ÁREA, no global)
--
--  tm_sede          Edificio administrativo, agencias y supermercado
--  tm_area          Áreas (con subáreas, estado activa/planificada y
--                   "a cargo de" otra área mientras no tenga personal)
--  tm_funcion       Responsable, Suplente, Colaborador, Pasante
--  td_area_usuario  Persona + área + sede + función + puesto
--
-- Tipos de cuenta (tm_rol): 1 Colaborador, 3 Administrador del sistema.
-- El antiguo rol 2 "Soporte" desaparece: atender tickets depende de
-- pertenecer a un área que atiende tickets (Informática).
--
-- Aplicar una sola vez, como root, con bd_copeba_tickets seleccionada.
-- Requiere 2026-11-24_prioridad.sql y 2026-11-24_bitacora.sql
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tm_sede` (
  `sede_id` int NOT NULL AUTO_INCREMENT,
  `sede_nom` varchar(80) COLLATE utf8mb4_spanish_ci NOT NULL,
  `sede_tipo` varchar(20) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'agencia',
  `sede_municipio` varchar(80) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `sede_departamento` varchar(80) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `est` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`sede_id`),
  UNIQUE KEY `uq_tm_sede_nom` (`sede_nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS `tm_area` (
  `area_id` int NOT NULL AUTO_INCREMENT,
  `area_nom` varchar(80) COLLATE utf8mb4_spanish_ci NOT NULL,
  `area_descrip` varchar(200) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `area_padre_id` int DEFAULT NULL,
  `area_cargo_id` int DEFAULT NULL,
  `area_estado` varchar(12) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'activa',
  `area_por_sede` tinyint NOT NULL DEFAULT 0,
  `area_atiende` tinyint NOT NULL DEFAULT 0,
  `est` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`area_id`),
  UNIQUE KEY `uq_tm_area_nom` (`area_nom`),
  KEY `fk_tm_area_padre` (`area_padre_id`),
  KEY `fk_tm_area_cargo` (`area_cargo_id`),
  CONSTRAINT `fk_tm_area_padre` FOREIGN KEY (`area_padre_id`) REFERENCES `tm_area` (`area_id`),
  CONSTRAINT `fk_tm_area_cargo` FOREIGN KEY (`area_cargo_id`) REFERENCES `tm_area` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci
  COMMENT='area_por_sede=1: existe en cada agencia; area_atiende=1: recibe tickets';

CREATE TABLE IF NOT EXISTS `tm_funcion` (
  `fun_id` int NOT NULL,
  `fun_nom` varchar(30) COLLATE utf8mb4_spanish_ci NOT NULL,
  `fun_descrip` varchar(120) COLLATE utf8mb4_spanish_ci NOT NULL,
  PRIMARY KEY (`fun_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS `td_area_usuario` (
  `au_id` int NOT NULL AUTO_INCREMENT,
  `usu_id` int NOT NULL,
  `area_id` int NOT NULL,
  `sede_id` int NOT NULL,
  `fun_id` int NOT NULL DEFAULT 3,
  `au_puesto` varchar(80) COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `fech_crea` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `est` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`au_id`),
  UNIQUE KEY `uq_area_usuario` (`usu_id`, `area_id`, `sede_id`),
  KEY `ix_area_usuario_area` (`area_id`, `sede_id`),
  CONSTRAINT `fk_au_usuario` FOREIGN KEY (`usu_id`) REFERENCES `tm_usuario` (`usu_id`),
  CONSTRAINT `fk_au_area` FOREIGN KEY (`area_id`) REFERENCES `tm_area` (`area_id`),
  CONSTRAINT `fk_au_sede` FOREIGN KEY (`sede_id`) REFERENCES `tm_sede` (`sede_id`),
  CONSTRAINT `fk_au_funcion` FOREIGN KEY (`fun_id`) REFERENCES `tm_funcion` (`fun_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ---------------------------------------------------------------------
-- Catálogos
-- ---------------------------------------------------------------------
INSERT INTO `tm_funcion` (`fun_id`, `fun_nom`, `fun_descrip`) VALUES
  (1,'Responsable','Encargado(a) del área o jefe(a) de agencia'),
  (2,'Suplente','Cubre al responsable en su ausencia'),
  (3,'Colaborador','Integrante del área (auxiliar, asesor, cajero, etc.)'),
  (4,'Pasante','Practicante temporal')
ON DUPLICATE KEY UPDATE `fun_descrip` = VALUES(`fun_descrip`);

INSERT IGNORE INTO `tm_sede` (`sede_nom`, `sede_tipo`, `sede_municipio`, `sede_departamento`) VALUES
  ('Edificio Administrativo',          'administrativa', 'Aldea Barraneché, Totonicapán', 'Totonicapán'),
  ('Agencia Totonicapán Central',      'agencia',        'Totonicapán',                   'Totonicapán'),
  ('Agencia La Esperanza',             'agencia',        NULL,                            NULL),
  ('Agencia La Concordia',             'agencia',        NULL,                            NULL),
  ('Agencia Chimente',                 'agencia',        NULL,                            'Totonicapán'),
  ('Agencia San Rafael Pie de la Cuesta','agencia',      'San Rafael Pie de la Cuesta',   'San Marcos'),
  ('Agencia Soloma',                   'agencia',        'San Pedro Soloma',              'Huehuetenango'),
  ('Agencia San Juan Ixcoy',           'agencia',        'San Juan Ixcoy',                'Huehuetenango'),
  ('Supermercado Comunitario',         'supermercado',   NULL,                            NULL);

-- Áreas activas
INSERT IGNORE INTO `tm_area` (`area_nom`, `area_descrip`, `area_por_sede`, `area_atiende`) VALUES
  ('Gerencia y Administración', 'Gerencia general, directivos y secretaría de administración', 0, 0),
  ('Consejo de Administración', 'Órgano de gobierno de la cooperativa', 0, 0),
  ('Cumplimiento',              'Oficial de cumplimiento, suplente y auxiliar', 0, 0),
  ('Contabilidad',              'Contador(a) general, auxiliares y pasantes', 0, 0),
  ('Jefatura de Agencia',       'Responsable de toda la agencia (Caja, Atención y Créditos)', 1, 0),
  ('Caja',                      'Receptores pagadores', 1, 0),
  ('Atención al Asociado',      'Secretaría y servicio al asociado', 1, 0),
  ('Créditos',                  'Asesores, oficial y administración de créditos', 1, 0),
  ('Mercadeo',                  'Promoción, imagen y eventos', 0, 0),
  ('Informática',               'Soporte técnico, sistemas y equipos de cómputo', 0, 1),
  ('Seguridad',                 'Seguridad de instalaciones y valores', 1, 0);

-- Subárea: Diseño dentro de Mercadeo
INSERT IGNORE INTO `tm_area` (`area_nom`, `area_descrip`, `area_padre_id`)
SELECT 'Diseño', 'Diseño gráfico y material publicitario', area_id FROM `tm_area` WHERE area_nom = 'Mercadeo';

-- Áreas planificadas: a cargo de Gerencia y Administración hasta tener personal
INSERT IGNORE INTO `tm_area` (`area_nom`, `area_descrip`, `area_estado`, `area_cargo_id`)
SELECT x.nom, x.descrip, 'planificada', a.area_id
FROM (
  SELECT 'Bodega' AS nom, 'Insumos, repuestos y almacén' AS descrip
  UNION ALL SELECT 'Compras', 'Adquisiciones y proveedores'
  UNION ALL SELECT 'Recursos Humanos', 'Personal, contratación y capacitación'
  UNION ALL SELECT 'Archivo', 'Resguardo de expedientes y documentos'
  UNION ALL SELECT 'Auditoría Interna', 'Revisión y control interno'
  UNION ALL SELECT 'Unidad de Riesgos', 'Gestión integral de riesgos'
  UNION ALL SELECT 'Cobros', 'Recuperación de cartera'
  UNION ALL SELECT 'Tesorería', 'Liquidez y manejo de fondos'
  UNION ALL SELECT 'Asesoría Jurídica', 'Contratos, garantías y temas legales'
  UNION ALL SELECT 'Servicios Generales', 'Mantenimiento, limpieza y vehículos'
  UNION ALL SELECT 'Comisión de Vigilancia', 'Órgano de control de la cooperativa'
) x CROSS JOIN `tm_area` a WHERE a.area_nom = 'Gerencia y Administración';

-- ---------------------------------------------------------------------
-- Cuentas: sede principal y paso de roles al modelo por áreas
-- ---------------------------------------------------------------------
ALTER TABLE `tm_usuario`
  ADD COLUMN `sede_id` int DEFAULT NULL AFTER `rol_id`,
  ADD KEY `fk_tm_usuario_sede` (`sede_id`),
  ADD CONSTRAINT `fk_tm_usuario_sede` FOREIGN KEY (`sede_id`) REFERENCES `tm_sede` (`sede_id`);

UPDATE `tm_usuario` SET `sede_id` = (SELECT sede_id FROM tm_sede WHERE sede_nom = 'Edificio Administrativo')
 WHERE `sede_id` IS NULL;

-- Administradores del sistema y antiguas cuentas de Soporte -> miembros de Informática
INSERT IGNORE INTO `td_area_usuario` (`usu_id`, `area_id`, `sede_id`, `fun_id`, `au_puesto`)
SELECT u.usu_id,
       (SELECT area_id FROM tm_area WHERE area_nom = 'Informática'),
       (SELECT sede_id FROM tm_sede WHERE sede_nom = 'Edificio Administrativo'),
       3,
       IF(u.rol_id = 3, 'Administrador de sistemas', 'Técnico de soporte')
FROM `tm_usuario` u WHERE u.rol_id IN (2, 3) AND u.est = 1;

-- El rol 2 deja de existir: esas cuentas pasan a Colaborador (su permiso viene de Informática)
UPDATE `tm_usuario` SET `rol_id` = 1 WHERE `rol_id` = 2;
DELETE FROM `tm_rol` WHERE `rol_id` = 2;
UPDATE `tm_rol` SET `rol_nom` = 'Colaborador' WHERE `rol_id` = 1;
UPDATE `tm_rol` SET `rol_nom` = 'Administrador del sistema' WHERE `rol_id` = 3;
