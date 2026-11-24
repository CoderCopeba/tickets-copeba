#!/usr/bin/env bash
# =====================================================================
# Copia la base LOCAL (bd_copeba_tickets) a la base en la NUBE (TiDB Cloud).
# Uso, desde la carpeta del proyecto en Git Bash:
#     bash instalar/nube/copiar_a_nube.sh
# Lee las credenciales de dos archivos que NO se suben a Git:
#     instalar/nube/local.cnf  (root local)       instalar/nube/nube.cnf  (usuario de TiDB)
# Ver instalar/nube/LEEME.md. Ninguna contraseña aparece en la pantalla.
# =====================================================================
set -euo pipefail
cd "$(dirname "$0")/../.."

BIN="/c/laragon/bin/mysql/$(ls /c/laragon/bin/mysql | head -1)/bin"
LOCAL="instalar/nube/local.cnf"
NUBE="instalar/nube/nube.cnf"
BASE="bd_copeba_tickets"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

for f in "$LOCAL" "$NUBE"; do
    [ -f "$f" ] || { echo "Falta $f (ver instalar/nube/LEEME.md)"; exit 1; }
done

echo "1/5 Probando conexión a la nube..."
"$BIN/mysql" --defaults-extra-file="$NUBE" -N -e "SELECT CONCAT('   Conectado a ', VERSION())"

echo "2/5 Respaldo de la base local (por si acaso)..."
mkdir -p /c/laragon/respaldos
"$BIN/mysqldump" --defaults-extra-file="$LOCAL" --single-transaction "$BASE" > "/c/laragon/respaldos/${BASE}_antes_de_subir_$(date +%Y%m%d_%H%M).sql"

echo "3/5 Exportando estructura y datos (sin procedimientos, sin DEFINER)..."
"$BIN/mysqldump" --defaults-extra-file="$LOCAL" --single-transaction --skip-triggers \
    --no-tablespaces --set-gtid-purged=OFF --skip-routines --skip-comments "$BASE" > "$TMP/base.sql" 2>/dev/null \
  || "$BIN/mysqldump" --defaults-extra-file="$LOCAL" --single-transaction --skip-triggers \
    --no-tablespaces --skip-routines --skip-comments "$BASE" > "$TMP/base.sql"
# Quitar DEFINER (por si alguna vista lo trae); el resto del volcado es compatible con TiDB
sed -i -E 's/ DEFINER=`[^`]+`@`[^`]+`//g' "$TMP/base.sql"

echo "4/5 Creando la base en la nube (si no existe) e importando..."
"$BIN/mysql" --defaults-extra-file="$NUBE" -e "CREATE DATABASE IF NOT EXISTS \`$BASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
"$BIN/mysql" --defaults-extra-file="$NUBE" "$BASE" < "$TMP/base.sql"

echo "5/5 Comprobando que coinciden..."
contar() { "$BIN/mysql" --defaults-extra-file="$1" -N "$BASE" -e \
  "SELECT CONCAT((SELECT COUNT(*) FROM tm_usuario),' usuarios / ',(SELECT COUNT(*) FROM tm_ticket),' tickets / ',(SELECT COUNT(*) FROM td_ticketdetalle),' respuestas / ',(SELECT COUNT(*) FROM tm_area),' áreas')"; }
echo "   Local: $(contar "$LOCAL")"
echo "   Nube:  $(contar "$NUBE")"
echo "Listo. Ahora cambia el .env (ver LEEME.md, paso 5)."
