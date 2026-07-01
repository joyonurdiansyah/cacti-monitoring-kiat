#!/usr/bin/env bash
set -euo pipefail

CACTI_ROOT=/var/www/html

# Read environment variables with defaults
DB_HOST="${CACTI_DB_HOST:-cacti-db}"
DB_PORT="${CACTI_DB_PORT:-3306}"
DB_NAME="${CACTI_DB_NAME:-cacti}"
DB_USER="${CACTI_DB_USER:-cactiuser}"
DB_PASS="${CACTI_DB_PASS:-cactipass}"
URL_PATH="${CACTI_URL_PATH:-/}"
TZ="${TZ:-Asia/Jakarta}"

log() { printf '[entrypoint] %s\n' "$*" >&2; }

# Set timezone
if [ -f /usr/share/zoneinfo/"${TZ}" ]; then
    ln -sf /usr/share/zoneinfo/"${TZ}" /etc/localtime
    echo "${TZ}" > /etc/timezone
fi

# Configure include/config.php from environment
CONFIG_PHP="${CACTI_ROOT}/include/config.php"
if [ ! -f "${CONFIG_PHP}" ]; then
    log "Creating include/config.php from .dist template"
    cp "${CACTI_ROOT}/include/config.php.dist" "${CONFIG_PHP}"
fi

log "Applying database configuration to include/config.php"
sed -i -E \
    -e "s|^(\\\$database_hostname[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_HOST}';|" \
    -e "s|^(\\\$database_username[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_USER}';|" \
    -e "s|^(\\\$database_password[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_PASS}';|" \
    -e "s|^(\\\$database_default[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_NAME}';|" \
    -e "s|^(\\\$database_port[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${DB_PORT}';|" \
    -e "s|^(\\\$url_path[[:space:]]*=[[:space:]]*)'[^']*';|\\1'${URL_PATH}';|" \
    "${CONFIG_PHP}"

# Wait for database
log "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
attempt=0
until php -r "
    \$m = @new mysqli('${DB_HOST}', '${DB_USER}', '${DB_PASS}', '${DB_NAME}', ${DB_PORT});
    if (\$m->connect_errno) { exit(1); }
    exit(0);
" 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "${attempt}" -ge 30 ]; then
        log "MySQL not reachable after 30 attempts"
        exit 1
    fi
    sleep 2
done
log "MySQL is reachable after ${attempt} attempt(s)"

# Check if Cacti is already installed (version table has actual version)
CACTI_VER=$(tr -d '[:space:]' < "${CACTI_ROOT}/include/cacti_version")
INSTALLED=$(php -r "
    \$m = new mysqli('${DB_HOST}', '${DB_USER}', '${DB_PASS}', '${DB_NAME}', ${DB_PORT});
    \$r = \$m->query(\"SELECT cacti FROM version WHERE cacti != 'new_install'\");
    echo \$r && \$r->num_rows > 0 ? 'yes' : 'no';
    \$m->close();
" 2>/dev/null || echo "no")

if [ "${INSTALLED}" = "no" ]; then
    log "Cacti not yet installed — importing schema..."

    # Count tables to see if schema exists
    TABLE_COUNT=$(php -r "
        \$m = new mysqli('${DB_HOST}', '${DB_USER}', '${DB_PASS}', '${DB_NAME}', ${DB_PORT});
        \$r = \$m->query('SHOW TABLES');
        echo \$r ? \$r->num_rows : 0;
        \$m->close();
    " 2>/dev/null || echo "0")

    if [ "${TABLE_COUNT}" -lt 10 ]; then
    log "Importing cacti.sql schema..."
    mysql \
        --host="${DB_HOST}" \
        --port="${DB_PORT}" \
        --user="${DB_USER}" \
        --password="${DB_PASS}" \
        --protocol=TCP \
        "${DB_NAME}" < "${CACTI_ROOT}/cacti.sql"
    fi

    # Mark installation as complete
    log "Marking Cacti version as ${CACTI_VER}"
    mysql \
        --host="${DB_HOST}" \
        --port="${DB_PORT}" \
        --user="${DB_USER}" \
        --password="${DB_PASS}" \
        --protocol=TCP \
        "${DB_NAME}" \
        --execute="UPDATE version SET cacti='${CACTI_VER}' WHERE cacti='new_install';"
    mysql \
        --host="${DB_HOST}" \
        --port="${DB_PORT}" \
        --user="${DB_USER}" \
        --password="${DB_PASS}" \
        --protocol=TCP \
        "${DB_NAME}" \
        --execute="INSERT INTO settings (name, value) VALUES ('install_complete','1') ON DUPLICATE KEY UPDATE value=VALUES(value);"

    log "Installation complete"
else
    log "Cacti already installed — skipping schema import"
fi

# Ensure writable directories
for d in log rra cache; do
    if [ -d "${CACTI_ROOT}/${d}" ]; then
        chmod -R a+w "${CACTI_ROOT}/${d}" 2>/dev/null || true
    fi
done

log "Starting services..."
exec "$@"
