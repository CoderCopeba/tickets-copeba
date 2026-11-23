# Instrucciones para agentes de IA (Copeba Tickets)

Este repositorio es el sistema de tickets de la Cooperativa Copeba, R.L.
Lo mantiene un equipo de tres personas. Antes de cambiar nada, lee este archivo completo.

## Reglas obligatorias

1. **Nunca trabajes en `main`.** Crea una rama propia desde `main` con un nombre nuevo
   (por ejemplo `juan/areas`, `jack/reportes`, `amilcar/inventario`). No reutilices
   `main`, `reestructuracion-bd` ni `actualizacion-librerias` salvo que el usuario lo pida.
   Los cambios llegan a `main` solo mediante Pull Request revisado por otra persona.
2. **No crees módulos que ya existen.** El sistema YA tiene: login, usuarios
   (`tm_usuario`, `models/Usuario.php`, `view/MntUsuario`), roles (`tm_rol`: 1 Usuario,
   2 Soporte, 3 Administrador), tickets, categorías, adjuntos y correo.
   Si "no encuentras la base de datos", el problema es de instalación (ver abajo),
   no falta un módulo: **no generes tablas, modelos ni pantallas de usuarios nuevas.**
3. **La base de datos se define en `docs/bd_copeba_tickets.sql`.** No inventes otro
   esquema ni renombres tablas o columnas. Si un cambio necesita tocar la base, escríbelo
   como script SQL nuevo y explícalo al usuario antes de ejecutarlo.
4. **No subas secretos.** `.env`, contraseñas, volcados con datos reales y la carpeta
   `vendor/` están en `.gitignore`. Nunca escribas contraseñas en código ni en commits.
5. **No hagas commit, push ni merge sin que el usuario lo pida.** Nunca uses `push --force`.
6. Antes de terminar: `php -l` en cada PHP modificado y `node --check` en cada JS modificado.

## Instalación local (Laragon: PHP 8.3+, MySQL 8.4)

1. Clonar en `C:\laragon\www\tickets-copeba`.
2. `composer install` (crea `vendor/` con PHPMailer).
3. Copiar `.env.example` a `.env` y pedir al usuario los datos (no los inventes).
4. En MySQL (HeidiSQL, como root): crear la base `bd_copeba_tickets` con
   `CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci` e importar `docs/bd_copeba_tickets.sql`.
5. Crear la cuenta de la aplicación con permisos mínimos (no usar root en `.env`):
   `SELECT, INSERT, UPDATE` sobre `bd_copeba_tickets.*` y `EXECUTE` sobre los 4
   procedimientos `sp_d_usuario_01`, `sp_i_ticketdetalle_01`, `sp_l_usuario_01`, `sp_l_usuario_02`.
6. Crear la primera cuenta Administrador (rol 3). Las contraseñas se guardan con
   `password_hash`; usa `Usuario::hash_pass()` o la pantalla Mantenimiento Usuario.
7. Abrir `http://localhost/tickets-copeba/`.

## Estructura

- `config/`: conexión (`conexion.php`), `.env` (`env.php`), permisos (`auth.php`),
  limpieza HTML anti-XSS (`html.php`), CSRF y límite de login (`seguridad.php`).
- `models/`: acceso a datos con PDO y consultas preparadas (nunca concatenar SQL).
- `controller/`: endpoints AJAX `?op=...`; cada uno valida sesión, rol y token CSRF.
- `view/`: pantallas PHP + JS (jQuery 3.7, DataTables 1.13, Summernote 0.9, Select2).
- `public/`: CSS, JS, imágenes y plantillas de correo. `public/document/` no es público:
  los adjuntos se sirven por `controller/documento.php?op=ver`.

## Convenciones

- Español en código, comentarios, mensajes y commits.
- Autorización siempre en el servidor (`requiere_sesion`, `requiere_rol`,
  `requiere_acceso_ticket`); ocultar botones en la vista no basta.
- Escapar toda salida con `e()`; HTML de usuarios pasa por `html_limpio()`.
- Archivos con finales de línea CRLF.
