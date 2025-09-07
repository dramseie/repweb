#!/usr/bin/env bash
# Save a “good” version to Git with a tag.
# Usage:
#   bin/git_save_version.sh                # auto tag like vYYYYMMDD-HHMMSS
#   bin/git_save_version.sh v1.2.3         # explicit tag
#   bin/git_save_version.sh v1.2.3 "note"  # explicit tag + message
set -euo pipefail

TAG="${1:-}"
MSG="${2:-}"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "❌ Not inside a Git repository."
  exit 1
fi

# Generate default tag if none provided
if [[ -z "$TAG" ]]; then
  TAG="v$(date +%Y%m%d-%H%M%S)"
fi

# Quick summary for message
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
DATE="$(date '+%Y-%m-%d %H:%M:%S %Z')"

SUMMARY=$(cat <<EOF
Good build on ${DATE}
Branch: ${BRANCH}
Node: $(node -v 2>/dev/null || echo 'n/a')
NPM: $(npm -v 2>/dev/null || echo 'n/a')
PHP: $(php -v | head -n1 2>/dev/null || echo 'n/a')
EOF
)

if [[ -z "$MSG" ]]; then
  MSG="Save good version: ${TAG}"
fi

echo "📝 Staging all changes…"
git add -A

# If nothing to commit, still allow tagging current commit
if git diff --cached --quiet; then
  echo "ℹ️ No changes to commit. Tagging current HEAD."
else
  echo "✅ Committing…"
  git commit -m "${MSG}" -m "${SUMMARY}"
fi

echo "🏷️ Creating tag ${TAG}…"
git tag -a "${TAG}" -m "${MSG}" -m "${SUMMARY}"

echo "🚀 To push:"
echo "    git push origin ${BRANCH}"
echo "    git push origin ${TAG}"
echo "✅ Done."
