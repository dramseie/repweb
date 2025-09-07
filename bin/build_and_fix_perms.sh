#!/usr/bin/env bash
# Build frontend, clear Symfony cache, and fix permissions
# Usage: bin/build_and_fix_perms.sh [project_root]
set -euo pipefail

ROOT="${1:-$(pwd)}"
cd "$ROOT"

# Detect web user/group (Rocky = apache:apache, Debian/Ubuntu = www-data:www-data)
WEB_USER="apache"
WEB_GROUP="apache"
if ! id -u "$WEB_USER" >/dev/null 2>&1; then
  WEB_USER="www-data"
fi
if ! getent group "$WEB_GROUP" >/dev/null 2>&1; then
  WEB_GROUP="$WEB_USER"
fi

echo "➡️ Using web user/group: ${WEB_USER}:${WEB_GROUP}"
echo "➡️ Project root: $ROOT"

# Ensure node_modules installed (only if package.json exists)
if [[ -f package.json ]]; then
  if [[ ! -d node_modules ]]; then
    echo "📦 Installing JS dependencies…"
    npm ci || npm install
  fi
  echo "🧱 Building frontend (npm run dev)…"
  npm run dev
else
  echo "⚠️ No package.json found — skipping frontend build."
fi

# Symfony cache clear + warmup
if [[ -x bin/console ]]; then
  echo "🧹 Clearing Symfony cache…"
  php -d detect_unicode=0 bin/console cache:clear --no-warmup || php bin/console cache:clear --no-warmup
  echo "🔥 Warming up Symfony cache…"
  php bin/console cache:warmup
else
  echo "⚠️ bin/console not found — skipping Symfony cache steps."
fi

# Create writable dirs if missing
mkdir -p var/cache var/log var/sessions

# Fix permissions (recursive, safe)
echo "🔐 Setting ownership to ${WEB_USER}:${WEB_GROUP}…"
chown -R "${WEB_USER}:${WEB_GROUP}" "$ROOT"

# Make sure web can write to var and public/build if present
echo "📝 Adjusting writable directories…"
chmod -R u+rwX,go+rX var || true
[[ -d public/build ]] && chmod -R u+rwX,go+rX public/build || true

echo "✅ Done."
