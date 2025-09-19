#!/usr/bin/env bash
# Save as: /usr/local/bin/repweb-zip-code.sh
# Usage: repweb-zip-code.sh

set -euo pipefail

TARGET="/mnt/repweb/code_review.zip"
ROOT="/var/www/html/repweb"

# Database connection (adjust or use ~/.my.cnf to avoid plaintext password)
DB_USER="webuser"
DB_PASS="!!repweb!!"

echo "➡️ Creating code review archive at $TARGET"

cd "$ROOT"

# Temporary dump file
DUMP_FILE="/tmp/all_databases_dump.sql"

echo "➡️ Dumping ALL databases"
mysqldump -u"$DB_USER" -p"$DB_PASS" --all-databases --single-transaction --routines --triggers > "$DUMP_FILE"

# Clean old zip if exists
rm -f "$TARGET"

# Zip main Symfony code directories + full DB dump
zip -r "$TARGET" \
  assets \
  templates \
  src \
  config \
  "$DUMP_FILE" \
  -x "*/node_modules/*" \
     "*/var/*" \
     "*/vendor/*" \
     "*.log" \
     "*.cache" \
     "*.lock"

# Remove temp dump
rm -f "$DUMP_FILE"

echo "✅ Archive created: $TARGET"
ls -lh "$TARGET"
