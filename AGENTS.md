# Instrucciones para agentes de IA (Copeba Tickets)

Sistema de tickets de la Cooperativa Copeba, R.L. Lo mantienen tres personas de Informática,
cada una con su propio agente. **Lee este archivo completo antes de cambiar nada.**

## Paso 0 — Antes de CUALQUIER tarea (obligatorio)

Ejecuta, desde la carpeta del proyecto:

```
php instalar/diagnostico.php
```

- Si termina en **"Todo en orden"** → puedes empezar la tarea.
- Si muestra **[FALLA]** → corrige SOLO eso siguiendo la sección "Puesta en marcha" o la
  tabla "Problemas frecuentes". **No empieces la tarea con fallas.**
- El diagnóstico nunca muestra contraseñas. Tú tampoco las muestres ni las escribas en comandos.

> Que la base parezca vacía, que el login no entre o que "falte" algo **nunca** significa que
> haya que crear tablas, usuarios o módulos nuevos. Casi siempre es el `.env` o la red.

## Cómo está montado (no lo cambies)

- **Base de datos compartida en la nube: TiDB Cloud** (compatible con MySQL 8). Todo el equipo
  usa la **misma** base: mismos usuarios y mismos tickets de prueba. No hay bases locales que
  sincronizar ni migraciones que aplicar en cada computadora.
- Cada persona tiene en su computadora: el código (Git), `vendor/` (Composer) y su `.env`.
  **El `.env` y las contraseñas nunca se suben a Git.**
- El sistema calcula solo su dirección (`Conectar::ruta()`), así que funciona aunque la carpeta
  tenga otro nombre. En un servidor se puede fijar con `APP_URL` en el `.env`.

## Puesta en marcha en una computadora nueva (Laragon, PHP 8.3+)

1. Clonar y ejecutar `composer install`:
   ```
   git clone https://github.com/CoderCopeba/tickets-copeba.git C:\laragon\www\tickets-copeba
   cd C:\laragon\www\tickets-copeba
   composer install
   ```
2. Copiar `.env.example` a `.env` y completar la parte de base de datos con los datos de la nube.
   **La persona** los pide a Informática y los escribe ella misma (en persona o desde un gestor
   de contraseñas; nunca por chat, correo ni Git):
   ```ini
   DB_HOST=gateway01.us-east-1.prod.aws.tidbcloud.com
   DB_PORT=4000
   DB_NAME=bd_copeba_tickets
   DB_USER=<prefijo>.tickets_app
   DB_PASSWORD=<la que entregue Informática>
   DB_SSL=1
   DB_SSL_CA=C:\laragon\etc\ssl\cacert.pem
   ```
   Si Laragon no está en `C:\laragon`, ajusta la ruta de `DB_SSL_CA` (el archivo debe existir).
3. `php instalar/diagnostico.php` → debe terminar en "Todo en orden".
4. Abrir la dirección que indica el diagnóstico (normalmente `http://localhost/tickets-copeba/`)
   y entrar con tu cuenta.

**Sin acceso a la nube (trabajo sin internet):** base local opcional; ver al final.

## Si ya tenías el proyecto (traer lo último)

Cuando se integra un PR en `main`, cada persona actualiza su computadora así:

1. Guarda o termina lo que tengas en tu rama (`git status` debe salir limpio).
   El repositorio está en **https://github.com/CoderCopeba/tickets-copeba**. Si `git remote -v`
   muestra otra dirección (la antigua `informatica1copeba/...`), cámbiala una vez:
   `git remote set-url origin https://github.com/CoderCopeba/tickets-copeba.git`
2. ```
   git switch main
   git pull
   composer install
   ```
3. Compara tu `.env` con `.env.example`: agrega las variables nuevas y borra las que ya no
   existan (por ejemplo, `CHAT_API_URL` y `CHAT_API_TOKEN` ya no se usan). No copies encima
   tu `.env`: perderías tus datos.
4. `php instalar/diagnostico.php` → "Todo en orden".
5. En el navegador, recarga con **Ctrl+F5** (para no usar archivos JS/CSS viejos).
6. Para seguir con tu trabajo: `git switch <tu-rama>` y `git merge main`.

No hace falta tocar la base: los cambios de estructura ya los aplicó un administrador en la nube.
Si el diagnóstico dice que faltan migraciones, avisa a Informática; no las ejecutes tú.

## Problemas frecuentes

| Síntoma | Causa | Qué hacer |
|---|---|---|
| El login aparece pero al pulsar "Acceder" no entra / página en blanco o "No se pudo conectar a la base de datos" | `.env` incompleto o con datos de otra persona | `php instalar/diagnostico.php` y corregir lo marcado como FALLA |
| "Usuario o contraseña de la base incorrectos" | `DB_USER` / `DB_PASSWORD` mal copiados | Volver a escribirlos (sin espacios ni comillas) |
| "Problema con la conexión cifrada" | Falta `DB_SSL=1` o `DB_SSL_CA` apunta a un archivo que no existe | Corregir la ruta del `cacert.pem` |
| "No se alcanza el servidor" / se queda cargando | Sin internet o la IP pública no está permitida en TiDB Cloud | Revisar internet; un administrador revisa TiDB Cloud → *Networking* |
| "El usuario y/o la contraseña son incorrectos" en el login | Contraseña de la **cuenta del sistema** equivocada | Informática genera una temporal en *Cuentas*; no se arregla con SQL |
| "Demasiados intentos fallidos" | 5 fallos seguidos → bloqueo de 15 min en esa computadora | Esperar 15 min |
| Te lleva a "Crea tu contraseña" | Cuenta con contraseña temporal | Es normal: crearla (dos veces) |
| Faltan menús que antes estaban | El menú depende del tipo de cuenta y del área | Revisar el área de la persona en *Usuarios* |

## Reglas obligatorias

1. **Nadie trabaja en `main`, ni personas ni agentes.** Antes de cambiar un archivo:
   ```
   git switch main
   git pull
   git switch -c <nombre>/<tarea>      (ej.: jack/reportes, juan/areas, amilcar/inventario)
   ```
   Los cambios llegan a `main` solo por **Pull Request** revisado por otra persona.
   Antes de abrir el PR: `git fetch` y `git merge origin/main` en tu rama para traer lo último.
2. **No crees módulos que ya existen.** El sistema YA tiene: login, usuarios
   (`models/Usuario.php`, `view/MntUsuario`), tipos de cuenta (`tm_rol`: 1 Colaborador,
   3 Administrador del sistema), áreas y sedes (`models/Area.php`, `view/MntArea`), cuentas y
   contraseñas temporales (`view/ControlUsuarios`), tickets con bitácora, prioridades y SLA,
   reportes (`view/Reportes`), "Ver como", adjuntos, correo y **tareas por área**
   (`models/Tarea.php`, `view/Tareas`: el Responsable o Suplente de un área asigna tareas a su gente)
   y **planificación semanal/mensual** (`models/Plan.php`, `view/Planificacion`).
   **Los permisos vienen del área**: quien pertenece a un área que atiende tickets (Informática)
   los gestiona. Usa `es_soporte()`, `es_admin()`, `puede_ver_reportes()`, `requiere_soporte()`;
   no compares números de rol a mano.
3. **La base es compartida: cuidado.** Lo que cambies en ella lo ven todos al instante.
   - **Nunca** ejecutes `DROP`, `TRUNCATE`, `DELETE` masivos ni cambies tablas sin que la persona
     lo autorice expresamente.
   - Un cambio de estructura = un archivo nuevo en `instalar/migraciones/AAAA-MM-DD_nombre.sql`,
     explicado a la persona, aplicado **una sola vez en la nube** por un administrador, y
     `docs/bd_copeba_tickets.sql` actualizado. Avisa al equipo en el PR.
   - La aplicación usa la cuenta `tickets_app` (solo SELECT, INSERT, UPDATE). La cuenta `.root`
     de TiDB es solo para migraciones; su archivo `instalar/nube/nube.cnf` no se sube.
4. **No subas secretos.** `.env`, `.env.*`, `*.cnf`, contraseñas, volcados con datos reales y
   `vendor/` están en `.gitignore`. No escribas contraseñas en código, commits ni comandos.
5. **No hagas commit, push ni merge sin que la persona lo pida.** Nunca uses `push --force`.
6. Antes de terminar: `php -l` en cada PHP modificado, `node --check` en cada JS modificado y
   `php instalar/diagnostico.php`.

## Reglas de SQL (TiDB Cloud)

- En `GROUP BY` incluye **todas** las columnas no agregadas del SELECT (TiDB es estricto).
- **No** uses procedimientos almacenados, triggers, eventos ni `utf8mb4_spanish_ci`.
  Cotejamiento: `utf8mb4_0900_ai_ci` (ignora mayúsculas y acentos).
- Siempre consultas preparadas con PDO (`?`); nunca concatenes datos del usuario en SQL.
- Pocas consultas por pantalla: el servidor está en Virginia (~150 ms por consulta medido desde
  Guatemala). Sesión y permisos ya vienen en UNA consulta por petición (`cuenta_sesion()` en
  `config/auth.php`): no vuelvas a consultar el área o el tipo de cuenta por separado. Las operaciones
  de solo lectura van en `operacion_de_lectura()`: así liberan la sesión y no hacen esperar a las demás.

## Estructura

- `config/`: conexión (`conexion.php`), `.env` (`env.php`), permisos (`auth.php`),
  limpieza HTML anti-XSS (`html.php`), CSRF y límite de login (`seguridad.php`), iconos.
- `models/`: acceso a datos con PDO.
- `controller/`: endpoints AJAX `?op=...`; cada uno valida sesión, permiso y token CSRF.
- `view/`: pantallas PHP + JS (jQuery 3.7, Summernote 0.9, Select2).
- `public/`: CSS, JS, imágenes y plantillas de correo. `public/document/` no es público:
  los adjuntos se sirven por `controller/documento.php?op=ver` (y se guardan en cada computadora).
- `instalar/`: diagnóstico, primer administrador, migraciones y guía de la nube (solo terminal).

## Convenciones

- **Reutiliza antes de escribir.** JavaScript: `public/js/copeba.js` (`Copeba.exito`, `aviso`,
  `error`, `confirmar`, `pedirTexto`, `dialogo`, `enviar`, `leer`, `editor`, `param`, `folio`,
  `fecha`, `estado`, `calendario`) y **`Copeba.listado`** (`public/js/copeba-listado.js`) para TODA lista.
  DataTables ya no está en el proyecto: no lo vuelvas a agregar. Todo `<input type="date">`
  usa solo el calendario propio (`Copeba.calendario`); si cambias su valor por código, añade
  `.trigger('cp-fecha')`. Iconos PHP: `icono("nombre")`. Estilos:
  `public/css/copeba-app.css`. Si algo se repite en dos pantallas, conviértelo en recurso compartido.
- Español en código, comentarios, mensajes y commits.
- Autorización siempre en el servidor; ocultar botones no basta.
- Escapar toda salida con `e()`; HTML de usuarios pasa por `html_limpio()`.
- Archivos con finales de línea CRLF (los `.sh` en LF).

## Anexo: base local sin internet (opcional)

Solo si hace falta trabajar sin conexión. En HeidiSQL, como `root`:
`CREATE DATABASE bd_copeba_tickets CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;`,
importar `docs/bd_copeba_tickets.sql`, crear `tickets_app` con
`GRANT SELECT, INSERT, UPDATE ON bd_copeba_tickets.*`, apuntar el `.env` a `127.0.0.1:3306`
(con `DB_SSL` vacío) y crear el primer administrador con `php instalar/crear_admin.php`
(lo ejecuta la persona). Los datos de esa base NO se comparten con el equipo.
