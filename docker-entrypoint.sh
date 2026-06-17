#!/bin/bash
set -e

# ── Secrets Manager: fetch Google Drive credentials ───────────────────────────
# Pull credentials JSON from Secrets Manager and write to disk.
# The env var GOOGLE_DRIVE_CREDENTIALS_JSON is no longer used — secret lives only in SM.
GOOGLE_JSON=$(aws secretsmanager get-secret-value \
  --secret-id srmis/google-drive-credentials \
  --region ap-southeast-1 \
  --query SecretString \
  --output text 2>/dev/null || echo "")

if [ -n "$GOOGLE_JSON" ]; then
    echo "$GOOGLE_JSON" > /var/www/google-credentials.json
    chmod 600 /var/www/google-credentials.json
    chown www-data:www-data /var/www/google-credentials.json
fi

# ── Secrets Manager: fetch Firebase service account ───────────────────────────
FIREBASE_JSON=$(aws secretsmanager get-secret-value \
  --secret-id srmis/firebase-service-account \
  --region ap-southeast-1 \
  --query SecretString \
  --output text 2>/dev/null || echo "")

if [ -n "$FIREBASE_JSON" ]; then
    FIREBASE_CREDS_PATH=/var/www/storage/app/firebase-service-account.json
    echo "$FIREBASE_JSON" > "$FIREBASE_CREDS_PATH"
    chmod 600 "$FIREBASE_CREDS_PATH"
    chown www-data:www-data "$FIREBASE_CREDS_PATH"
    export FIREBASE_CREDENTIALS="$FIREBASE_CREDS_PATH"
fi

# ── Export env vars for cron (restricted to non-secret vars) ──────────────────
# Exclude secrets — DB_PASSWORD, APP_KEY, SOKETI secret are injected by ECS
# at runtime and available to processes; cron reads /etc/environment on start.
printenv | grep -vE "^(no_proxy|GOOGLE_DRIVE_CREDENTIALS_JSON|LS_COLORS|GPG_KEYS|PHP_(ASC|SHA|CFLAGS|CPPFLAGS|LDFLAGS|URL|INI|VERSION)|PHPIZE_DEPS)" \
  > /etc/environment
chmod 600 /etc/environment

# ── Ensure writable directories are owned by www-data ─────────────────────────
chown -R www-data:www-data \
    /var/www/storage \
    /var/www/bootstrap/cache

# ── Ensure the central database exists (fresh RDS has none) ──────────────────
if [ -n "$DB_HOST" ] && [ -n "$DB_DATABASE" ]; then
    mysql --skip-ssl -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
        -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        || echo "WARN: could not ensure central database exists"
fi

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
php /var/www/artisan cache:clear
# Central schema first (tenants/domains/settings), then every tenant schema.
php /var/www/artisan migrate --force
php /var/www/artisan db:seed --class=SuperAdminSeeder --force
php /var/www/artisan tenants:migrate || true
# app_versions is a TENANT table — sync per tenant, never fatally
php /var/www/artisan tenants:run app:version-sync || true
php /var/www/artisan config:cache
php /var/www/artisan route:cache
php /var/www/artisan view:cache

# Signal any queue worker from a previous (still draining) ECS task to exit
# gracefully so it stops processing jobs with the OLD codebase. The fresh
# worker started below by supervisord will pick up the new code.
# Prevents "__PHP_Incomplete_Class" errors on jobs dispatched right after a deploy.
php /var/www/artisan queue:restart || true

# ── Start services via supervisord ─────────────────────────────────────────────
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/srmis.conf
