#!/usr/bin/env bash
# Build frontend, clear Symfony cache, and fix permissions
# Always runs from /var/www/html/repweb, regardless of where it's called from.
set -euo pipefail

ROOT="/var/www/html/repweb"

if [[ ! -d "$ROOT" ]]; then
  echo "❌ Project root not found: $ROOT"
  exit 1
fi

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

# ---------------------------
# Frontend build (if present)
# ---------------------------
if [[ -f package.json ]]; then
  if [[ ! -d node_modules ]]; then
    echo "📦 Installing JS dependencies…"
    if command -v npm >/dev/null 2>&1; then
      [[ -f package-lock.json ]] && (npm ci || npm install) || npm install
    else
      echo "⚠️ npm not found — skipping frontend dependency install."
    fi
  fi
  if command -v npm >/dev/null 2>&1; then
    echo "🧱 Building frontend (npm run dev)…"
    npm run dev
  else
    echo "⚠️ npm not found — skipping frontend build."
  fi
else
  echo "⚠️ No package.json found — skipping frontend build."
fi

# ---------------------------------------------------
# Symfony detection (honor $SYMFONY_DIR if provided)
# ---------------------------------------------------
SYMFONY_DIR="${SYMFONY_DIR:-}"

if [[ -z "${SYMFONY_DIR}" ]]; then
  if [[ -f "bin/console" ]]; then
    SYMFONY_DIR="$ROOT"
  else
    FOUND_CONSOLE="$(find "$ROOT" -maxdepth 3 -type f -path '*/bin/console' | head -n1 || true)"
    if [[ -n "$FOUND_CONSOLE" ]]; then
      SYMFONY_DIR="$(dirname "$(dirname "$FOUND_CONSOLE")")"
    fi
  fi
fi

if [[ -n "${SYMFONY_DIR}" && -f "${SYMFONY_DIR}/bin/console" ]]; then
  echo "✅ Symfony detected at: ${SYMFONY_DIR}"

  # Ensure PHP deps if composer.json exists but vendor/ is missing
  if [[ -f "${SYMFONY_DIR}/composer.json" && ! -d "${SYMFONY_DIR}/vendor" ]]; then
    if command -v composer >/dev/null 2>&1; then
      echo "📦 Installing PHP dependencies (composer install)…"
      (cd "${SYMFONY_DIR}" && composer install --no-interaction --prefer-dist)
    else
      echo "⚠️ composer not found — skipping PHP dependency install."
    fi
  fi

  # Always call via php, even if console isn't +x
  CONSOLE_CMD=(php "${SYMFONY_DIR}/bin/console")

  echo "🧹 Clearing Symfony cache…"
  (cd "${SYMFONY_DIR}" && "${CONSOLE_CMD[@]}" cache:clear --no-warmup) || true

  echo "🔥 Warming up Symfony cache…"
  (cd "${SYMFONY_DIR}" && "${CONSOLE_CMD[@]}" cache:warmup) || true
else
  echo "⚠️ bin/console not found — skipping Symfony cache steps."
  echo "   (Tip: set SYMFONY_DIR=/path/to/app if your backend is in a subfolder.)"
fi

# ---------------------------
# Writable directories
# ---------------------------
mkdir -p var/cache var/log var/sessions
mkdir -p public/build
if [[ -n "${SYMFONY_DIR:-}" && -d "${SYMFONY_DIR}/var" ]]; then
  mkdir -p "${SYMFONY_DIR}/var/cache" "${SYMFONY_DIR}/var/log" "${SYMFONY_DIR}/var/sessions"
fi

# ---------------------------
# Fix permissions
# ---------------------------
echo "🔐 Setting ownership to ${WEB_USER}:${WEB_GROUP}…"
chown -R "${WEB_USER}:${WEB_GROUP}" "$ROOT"

echo "📝 Adjusting writable directories…"
chmod -R u+rwX,go+rX var public/build || true
if [[ -n "${SYMFONY_DIR:-}" && -d "${SYMFONY_DIR}/var" ]]; then
  chmod -R u+rwX,go+rX "${SYMFONY_DIR}/var" || true
fi

echo "✅ Done."
