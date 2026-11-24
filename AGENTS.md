# Instrucciones para agentes de IA (Copeba Tickets)

Este repositorio es el sistema de tickets de la Cooperativa Copeba, R.L.
Lo mantiene un equipo de tres personas. Antes de cambiar nada, lee este archivo completo.

## Paso 0: comprobar la instalación (SIEMPRE, antes de cualquier tarea)

Cada persona tiene su **propia** instalación local: su propio `.env`, su propia base de
datos y su propia cuenta Administrador. Nada de eso viene en el repositorio ni se sube.
Antes de empezar la tarea, comprueba y avisa al usuario de lo que falte:

- [ ] Existe `vendor/autoload.php`. Si no: ejecuta `composer install`.
- [ ] Existe `.env`. Si no: sigue el paso 2 de "Instalación local".
- [ ] La base `bd_copeba_tickets` existe y tiene las tablas `tm_usuario`, `tm_ticket`, etc.
      Si no: sigue los pasos 3 y 4. **No crees tablas a mano ni inventes un esquema.**
- [ ] Hay al menos un usuario Administrador. Si no: paso 5 (lo ejecuta la persona).
- [ ] `http://localhost/tickets-copeba/` muestra el login y se puede entrar.

Si algo de esto falla, **termina primero la instalación y no empieces la tarea.**
Que la base esté vacía o no exista nunca significa que falte un módulo.

## Reglas obligatorias

1. **Nadie trabaja en `main`, ni las personas ni los agentes.** Antes de cambiar
   cualquier archivo, crea una rama propia desde `main` actualizado:
   ```
   git switch main
   git pull
   git switch -c <nombre>/<tarea>      (ej.: juan/areas, jack/reportes, amilcar/inventario)
   ```
   No reutilices `main`, `reestructuracion-bd`, `actualizacion-librerias` ni
   `instalacion-inicial` salvo que el usuario lo pida. Los cambios llegan a `main` solo
   mediante Pull Request revisado por otra persona del equipo.
2. **No crees módulos que ya existen.** El sistema YA tiene: login, usuarios
   (`tm_usuario`, `models/Usuario.php`, `view/MntUsuario`), tipos de cuenta (`tm_rol`: 1 Colaborador,
   3 Administrador del sistema), áreas y sedes (`tm_area`, `tm_sede`, `td_area_usuario`,
   `models/Area.php`, `view/MntArea`), tickets, categorías, prioridades, adjuntos y correo.
   **Los permisos de trabajo vienen del área** (modelo tipo Canvas): quien pertenece a un
   área que atiende tickets (Informática) los gestiona. Usa `es_soporte()`, `es_admin()`,
   `requiere_soporte()`; no compares números de rol a mano.
   **No generes tablas, modelos ni pantallas de usuarios nuevas.**
3. **La base de datos se define en `docs/bd_copeba_tickets.sql`.** No inventes otro
   esquema ni renombres tablas o columnas. Si un cambio necesita tocar la base, escríbelo
   como script SQL nuevo, actualiza `docs/bd_copeba_tickets.sql` y explícalo al usuario
   antes de ejecutarlo.
4. **No subas secretos.** `.env`, contraseñas, volcados con datos reales y la carpeta
   `vendor/` están en `.gitignore`. Nunca escribas contraseñas en código, commits ni
   comandos; pide al usuario que las escriba él.
5. **No hagas commit, push ni merge sin que el usuario lo pida.** Nunca uses `push --force`.
6. Antes de terminar: `php -l` en cada PHP modificado y `node --check` en cada JS modificado.

## Instalación local (Laragon: PHP 8.3+, MySQL 8.4)

1. **Código y dependencias.** Clonar en `C:\laragon\www\tickets-copeba` y ejecutar
   `composer install` (crea `vendor/` con PHPMailer).
2. **Archivo `.env`.** Copiar `.env.example` a `.env`. Ya trae los valores locales
   estándar (`DB_HOST=127.0.0.1`, `DB_NAME=bd_copeba_tickets`, `DB_USER=tickets_app`).
   La persona escribe ella misma `DB_PASSWORD` (la que use en el paso 4). Los `MAIL_*`
   pueden quedar vacíos en local: entonces no se envían correos.
3. **Base de datos.** En HeidiSQL o Workbench, conectado como `root`:
   ```sql
   CREATE DATABASE bd_copeba_tickets CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   ```
   Seleccionar `bd_copeba_tickets` y cargar/ejecutar el archivo `docs/bd_copeba_tickets.sql`
   (tablas, roles, categorías, prioridades, funciones, sedes y áreas; sin procedimientos).
   **Si el equipo usa la base compartida en la nube (TiDB Cloud), no hace falta base local:**
   ver `instalar/nube/LEEME.md`. No uses procedimientos almacenados ni `utf8mb4_spanish_ci`
   (TiDB no los admite).
4. **Cuenta MySQL de la aplicación** (la app nunca usa `root`). Como `root`, cambiando
   `CLAVE_LOCAL` por una contraseña propia, igual a `DB_PASSWORD` del `.env`:
   ```sql
   CREATE USER 'tickets_app'@'127.0.0.1' IDENTIFIED BY 'CLAVE_LOCAL';
   GRANT SELECT, INSERT, UPDATE ON bd_copeba_tickets.* TO 'tickets_app'@'127.0.0.1';
   ```
   No guardes este script con la contraseña escrita dentro del proyecto.
5. **Primer Administrador.** La persona (no el agente) ejecuta en una terminal, desde la
   carpeta del proyecto:
   ```
   php instalar/crear_admin.php
   ```
   y escribe usuario, nombre, correo y contraseña. **No crees usuarios por SQL.**
   Las demás cuentas se crean desde la pantalla Mantenimiento Usuario.
6. **Comprobar.** Abrir `http://localhost/tickets-copeba/` y entrar con esa cuenta.

### Si la base cambia (otra rama añadió tablas o columnas)

Después de `git pull`, si `docs/bd_copeba_tickets.sql` cambió, aplica solo el script de
cambios que acompañe al Pull Request (no borres tu base si tiene datos de prueba que
quieras conservar). Si no hay script, pregunta al equipo antes de reimportar.

## Estructura

- `config/`: conexión (`conexion.php`), `.env` (`env.php`), permisos (`auth.php`),
  limpieza HTML anti-XSS (`html.php`), CSRF y límite de login (`seguridad.php`).
- `models/`: acceso a datos con PDO y consultas preparadas (nunca concatenar SQL).
- `controller/`: endpoints AJAX `?op=...`; cada uno valida sesión, rol y token CSRF.
- `view/`: pantallas PHP + JS (jQuery 3.7, DataTables 1.13, Summernote 0.9, Select2).
- `public/`: CSS, JS, imágenes y plantillas de correo. `public/document/` no es público:
  los adjuntos se sirven por `controller/documento.php?op=ver`.
- `instalar/`: scripts de instalación solo para terminal (bloqueados desde la web).

## Convenciones

- **Reutiliza antes de escribir.** JavaScript: `public/js/copeba.js` (`Copeba.exito`, `aviso`,
  `error`, `confirmar`, `pedirTexto`, `dialogo`, `enviar`, `leer`, `editor`, `param`, `folio`, `fecha`,
  `estado`) y **`Copeba.listado`** (`public/js/copeba-listado.js`) para TODA lista: pestañas,
  buscador, exportar CSV, paginación y "Mostrar por página". No uses DataTables en pantallas nuevas.
  Iconos PHP: `icono("nombre")` de `config/iconos.php`. Estilos comunes: `public/css/copeba-app.css`.
  Si algo se repite en dos pantallas, conviértelo en recurso compartido.

- Español en código, comentarios, mensajes y commits.
- Autorización siempre en el servidor (`requiere_sesion`, `requiere_rol`,
  `requiere_acceso_ticket`); ocultar botones en la vista no basta.
- Escapar toda salida con `e()`; HTML de usuarios pasa por `html_limpio()`.
- **La base compartida es TiDB Cloud (compatible con MySQL)**: en `GROUP BY` incluye todas las
  columnas no agregadas del SELECT; no uses procedimientos almacenados, triggers ni
  `utf8mb4_spanish_ci`. Prueba las consultas nuevas también contra la nube.
- Archivos con finales de línea CRLF.
