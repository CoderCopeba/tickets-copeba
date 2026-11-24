-- =====================================================================
-- Migración 2026-11-24 (4): la sede de Barraneché es central
-- En el mismo edificio están las oficinas administrativas (pisos de
-- arriba, a cargo de la gerente general) y una agencia completa (planta
-- baja: jefa de agencia, cajeras y atención al asociado).
-- Aplicar después de 2026-11-24_areas_sedes.sql
-- =====================================================================
SET NAMES utf8mb4;

UPDATE `tm_sede`
   SET `sede_nom` = 'Sede Central Barraneché', `sede_tipo` = 'central'
 WHERE `sede_nom` = 'Edificio Administrativo';

UPDATE `tm_area`
   SET `area_descrip` = 'Gerente general (dirige la parte administrativa), directivos y secretaría de administración'
 WHERE `area_nom` = 'Gerencia y Administración';

UPDATE `tm_area`
   SET `area_descrip` = 'Jefe(a) de cada agencia, incluida la de la sede central: Caja, Atención y Créditos'
 WHERE `area_nom` = 'Jefatura de Agencia';
