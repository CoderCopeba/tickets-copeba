# Copeba Tickets

Sistema de reporte y soporte técnico de la Cooperativa Copeba, R.L.

## Requisitos

- PHP 8.3 o superior (extensiones `pdo_mysql`, `mbstring`, `gd`, `openssl`)
- MySQL 8.4
- [Composer](https://getcomposer.org/) (incluido en Laragon)

## Instalación

1. Clonar el repositorio en la carpeta del servidor web (en Laragon: `C:\laragon\www\tickets-copeba`).
2. Instalar las dependencias de PHP:
   ```
   composer install --no-dev
   ```
3. Copiar `.env.example` a `.env` y completar los datos de la base de datos, del correo (`MAIL_*`) y del contacto de soporte.
4. Crear la base `bd_copeba_tickets` (utf8mb4) e importar `docs/bd_copeba_tickets.sql`.
5. Crear un usuario de MySQL con permisos mínimos para la aplicación (SELECT, INSERT, UPDATE y EXECUTE de los procedimientos) y ponerlo en `.env`. No usar `root`.

## Ramas

- `main`: versión estable.
- `reestructuracion-bd`: cambios de la base de datos (áreas, prioridades, etc.).
- `actualizacion-librerias`: actualización de dependencias y seguridad.

## Librerías principales

| Librería | Versión |
|---|---|
| PHPMailer (Composer) | 7.2 |
| jQuery (+ Migrate) | 3.7.1 (3.5.2) |
| DataTables | 1.13.11 |
| Summernote (lite) | 0.9.1 |
| Select2 | 4.0.13 |
| Bootstrap (plantilla StartUI) | 4.0.0-alpha.4 |
