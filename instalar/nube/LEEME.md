# Pasar la base de datos a la nube (TiDB Cloud Starter, gratis)

Una sola base compartida para todo el equipo: mismos usuarios y mismos tickets de prueba,
sin conflictos entre computadoras. **Solo para pruebas y desarrollo**: antes de guardar datos
reales de asociados en la nube, confirmarlo con Gerencia y Cumplimiento.

## 1. Crear la cuenta (lo hace una persona, una sola vez)

1. Entrar a <https://tidbcloud.com> → *Sign up* (con el correo de Informática).
2. Crear un clúster **Starter** (gratis). Región: la más cercana disponible (Estados Unidos).
   Nombre sugerido: `copeba-tickets`.
3. En el clúster → **Connect**:
   - *Connect with*: **General** · *Operating system*: **Windows**.
   - Pulsar **Generate password** y **guardarla en un lugar seguro** (no se vuelve a mostrar).
   - Anotar **Host**, **Port** (4000) y **User** (se ve como `xxxxxxxx.root`).

## 2. Archivos de conexión (NO se suben a Git)

Crear `instalar/nube/nube.cnf` (Bloc de notas) con los datos del paso 1:

```ini
[client]
host=gateway01.xxxxx.prod.aws.tidbcloud.com
port=4000
user=xxxxxxxx.root
password=LA_CONTRASEÑA_GENERADA
ssl-ca=C:/laragon/etc/ssl/cacert.pem
ssl-mode=VERIFY_IDENTITY
default-character-set=utf8mb4
```

Crear `instalar/nube/local.cnf` con el root de Laragon:

```ini
[client]
host=127.0.0.1
user=root
password=CONTRASEÑA_DE_ROOT_LOCAL
default-character-set=utf8mb4
```

## 3. Copiar la base local a la nube

En **Git Bash**, desde la carpeta del proyecto:

```bash
bash instalar/nube/copiar_a_nube.sh
```

Hace un respaldo local, exporta, importa en la nube y muestra los conteos de las dos bases
para comparar.

## 4. Cuenta de la aplicación en la nube (permisos mínimos)

La aplicación no debe usar el usuario `.root`. Con el prefijo de tu usuario (lo que va antes
del punto, p. ej. `xxxxxxxx`), ejecutar en el **SQL Editor** de TiDB Cloud:

```sql
CREATE USER 'xxxxxxxx.tickets_app'@'%' IDENTIFIED BY 'OTRA_CONTRASEÑA_LARGA';
GRANT SELECT, INSERT, UPDATE ON bd_copeba_tickets.* TO 'xxxxxxxx.tickets_app'@'%';
```

## 5. Apuntar el sistema a la nube

En el `.env` de **cada** computadora:

```ini
DB_HOST=gateway01.xxxxx.prod.aws.tidbcloud.com
DB_PORT=4000
DB_NAME=bd_copeba_tickets
DB_USER=xxxxxxxx.tickets_app
DB_PASSWORD=OTRA_CONTRASEÑA_LARGA
DB_SSL=1
DB_SSL_CA=C:\laragon\etc\ssl\cacert.pem
```

Abrir <http://localhost/tickets-copeba/> y entrar. Para volver a la base local basta con
restaurar los valores anteriores del `.env`.

## Notas

- **Velocidad**: la base está en Virginia; con conexiones persistentes cada pantalla tarda ~0,4 s.
- **La contraseña de `tickets_app`** está solo en el `.env` de quien la creó: compártanla en persona
  o por un gestor de contraseñas, nunca por chat, correo ni Git.
- **Plan gratuito**: hasta 5 clústeres por organización con una cuota mensual de
  almacenamiento y "Request Units". Si se agota, la base se vuelve lenta hasta el mes
  siguiente (no cobra sin un límite de gasto configurado).
- **Compatibilidad**: TiDB no admite procedimientos almacenados ni `utf8mb4_spanish_ci`;
  por eso desde la migración `2026-11-25_compatibilidad_nube.sql` el sistema no los usa.
- **Adjuntos**: los archivos de `public/document/` siguen guardándose en cada computadora;
  la base en la nube solo guarda su registro. (Para compartirlos haría falta un
  almacenamiento de archivos en la nube; queda pendiente.)
- **Migraciones futuras**: se aplican una vez en la nube (SQL Editor o `mysql` con `nube.cnf`)
  y ya quedan para todo el equipo.
