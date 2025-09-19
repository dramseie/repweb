#!/usr/bin/env bash
# bin/symf-page-deps.sh
# Discover files involved in rendering a Symfony page:
# route → controller → injected services → twig templates (includes/extents) → Encore entries → asset sources
#
# Usage:
#   bin/symf-page-deps.sh <route_name | path>
# Examples:
#   bin/symf-page-deps.sh plotly_page
#   bin/symf-page-deps.sh /plotly/123
#
set -euo pipefail

ROOT="$(pwd)"
CONSOLE="php bin/console"
ARG="${1:-}"
if [[ -z "${ARG}" ]]; then
  echo "Usage: $0 <route_name | path>"; exit 1
fi

# Helpers
hr() { printf '%s\n' '--------------------------------------------------------------------------------'; }
indent() { sed 's/^/  /'; }
exists() { command -v "$1" >/dev/null 2>&1; }

# Ensure we’re in a Symfony app
if [[ ! -f bin/console || ! -d vendor ]]; then
  echo "❌ Run this from your Symfony project root (bin/console not found)."; exit 1
fi

# 1) Find route metadata (route name, path, controller)
ROUTE_JSON="$($CONSOLE debug:router --format=json)"
php -r '
  $arg = $argv[1];
  $routes = json_decode(stream_get_contents(STDIN), true);
  $byName = null; $byPath = null;

  // Try exact route name first
  if (isset($routes[$arg])) { $byName = $routes[$arg]; $byName["_name"] = $arg; }

  // Otherwise, try to match by (sub)path
  if (!$byName && str_starts_with($arg, "/")) {
    foreach ($routes as $name => $r) {
      // Prefer exact path, otherwise keep best-looking match
      if (!empty($r["path"])) {
        if ($r["path"] === $arg) { $byPath = $r; $byPath["_name"] = $name; break; }
        if (strpos($r["path"], $arg) !== false) { $byPath = $r; $byPath["_name"] = $name; }
      }
    }
  }

  $pick = $byName ?: $byPath;
  if (!$pick) { fwrite(STDERR, "No route matched: $arg\n"); exit(2); }

  // Emit TSV: name  path  controller
  $ctrl = $pick["defaults"]["_controller"] ?? ($pick["controller"] ?? "");
  echo $pick["_name"], "\t", ($pick["path"] ?? ""), "\t", $ctrl, "\n";
' "$ARG" <<<"$ROUTE_JSON" > /tmp/route_pick.tsv || { echo "❌ Could not find route for: $ARG"; exit 2; }

ROUTE_NAME="$(cut -f1 /tmp/route_pick.tsv)"
ROUTE_PATH="$(cut -f2 /tmp/route_pick.tsv)"
ROUTE_CTRL="$(cut -f3 /tmp/route_pick.tsv)"

echo "🧭 Route: $ROUTE_NAME"
echo "🛣️  Path : $ROUTE_PATH"
echo "🎯 Ctrl : $ROUTE_CTRL"
hr

# 2) Resolve controller class & file and list injected services (constructor)
CLASS="${ROUTE_CTRL%%::*}"
METHOD="${ROUTE_CTRL##*::}"

php -r '
  require __DIR__."/vendor/autoload.php";
  $class = $argv[1];
  if (!class_exists($class)) { fwrite(STDERR, "Class not autoloadable: $class\n"); exit(3); }
  $rc = new ReflectionClass($class);
  $file = $rc->getFileName();
  echo "CLASS_FILE\t$file\n";

  $ctor = $rc->getConstructor();
  if ($ctor) {
    foreach ($ctor->getParameters() as $p) {
      $t = $p->getType();
      $name = $p->getName();
      $fqcn = ($t && !$t->isBuiltin()) ? ($t instanceof ReflectionNamedType ? $t->getName() : (string)$t) : "";
      echo "CTOR_PARAM\t$name\t$fqcn\n";
    }
  }
' "$CLASS" > /tmp/controller_reflect.tsv

CTRL_FILE="$(awk -F'\t' '$1=="CLASS_FILE"{print $2}' /tmp/controller_reflect.tsv)"
echo "📄 Controller file"
echo "└─ $CTRL_FILE"
hr

echo "🧩 Injected services (constructor)"
awk -F'\t' '$1=="CTOR_PARAM"{printf "└─ $%s : %s\n",$2, ($3 ? $3 : "(scalar/none)")}' /tmp/controller_reflect.tsv | ( [[ -s /dev/stdin ]] && cat || echo "└─ (none or property injection)" )
hr

# 3) Extract templates used via $this->render('...')
echo "🖼️  Twig templates (direct render/renderForm/twig->render calls)"
TEMPLATES=()
if [[ -f "$CTRL_FILE" ]]; then
  mapfile -t TEMPLATES < <(
    {
      grep -oE "->render\([[:space:]]*['\"][^'\"]+['\"]" -- "$CTRL_FILE"
      grep -oE "->renderForm\([[:space:]]*['\"][^'\"]+['\"]" -- "$CTRL_FILE"
      grep -oE "\$twig->render\([[:space:]]*['\"][^'\"]+['\"]" -- "$CTRL_FILE"
    } 2>/dev/null \
    | sed -E "s/.*\(['\"]([^'\"]+)['\"].*/\1/" \
    | sort -u
  )
fi
if [[ ${#TEMPLATES[@]} -eq 0 ]]; then
  echo "└─ (no template calls found; maybe JSON/API route)"
else
  for tpl in "${TEMPLATES[@]}"; do
    [[ -n "$tpl" ]] && echo "└─ $tpl"
  done
fi
hr


# Helper to map a twig logical name to filesystem path (best-effort)
twig_to_path() {
  local logical="$1"
  if [[ "$logical" =~ ^@ ]]; then
    # Namespaced templates (@Something/...) – non-trivial to resolve without Kernel; show logical only
    echo ""
    return 0
  fi
  echo "$ROOT/templates/$logical"
}

# 4) Recursively discover {% extends %}, {% include %}, {% import %}, {% embed %}
declare -A VISITED
declare -a QUEUE
QUEUE=()  # explicit init, avoids nounset issues

# seed queue
for t in "${TEMPLATES[@]:-}"; do
  [[ -n "$t" ]] && QUEUE+=("$t")
done

echo "🧵 Twig include/extend graph"
if [[ ${#QUEUE[@]} -eq 0 ]]; then
  echo "└─ (no main templates)"
else
  while [[ ${#QUEUE[@]} -gt 0 ]]; do
    cur="${QUEUE[0]}"
    # pop head safely
    if [[ ${#QUEUE[@]} -gt 1 ]]; then
      QUEUE=("${QUEUE[@]:1}")
    else
      QUEUE=()
    fi

    # skip empties / already visited
    [[ -z "${cur:-}" ]] && continue
    [[ -n "${VISITED[$cur]:-}" ]] && continue
    VISITED["$cur"]=1

    fpath="$(twig_to_path "$cur")"
    if [[ -n "$fpath" && -f "$fpath" ]]; then
      echo "└─ $cur"
      mapfile -t refs < <(
        grep -oE "{%[[:space:]]*(extends|include|import|embed)[[:space:]]+['\"][^'\"]+['\"]" -- "$fpath" 2>/dev/null \
        | sed -E "s/.*['\"]([^'\"]+)['\"].*/\1/" \
        | sort -u
      )
      for r in "${refs[@]:-}"; do
        echo "   └─ uses: $r"
        [[ -n "$r" ]] && QUEUE+=("$r")
      done
    else
      echo "└─ $cur (file not found or namespaced)"
    fi
  done
fi
hr


# 5) Find Encore entry names in all discovered twig files
echo "📦 Webpack Encore entries (from Twig)"
declare -A TWIG_FILES
for k in "${!VISITED[@]}"; do
  p="$(twig_to_path "$k")"
  [[ -n "$p" && -f "$p" ]] && TWIG_FILES["$p"]=1
done
mapfile -t ENTRIES < <(
  (for p in "${!TWIG_FILES[@]:-}"; do cat "$p"; done) 2>/dev/null \
  | grep -oE "encore_entry_(script|link)_tags\(\s*'[^']+'|encore_entry_(script|link)_tags\(\s*\"[^\"]+\"" \
  | sed -E "s/.*\(\s*['\"]([^'\"]+)['\"].*/\1/" | sort -u
)
if [[ ${#ENTRIES[@]} -eq 0 ]]; then
  echo "└─ (no Encore entries referenced)"
else
  for e in "${ENTRIES[@]}"; do echo "└─ $e"; done
fi
hr

# 6) Try to map Encore entries to source files (best effort)
echo "🗂️  Candidate asset source files"
FOUND_ANY=0

# a) Look into webpack.config.js addEntry('name', 'path')
if [[ -f webpack.config.js ]]; then
  while IFS= read -r e; do
    # Escaped single quotes in sed pattern
    pat="addEntry\\(['\"]${e}['\"]\\s*,\\s*['\"][^'\"]+['\"]\\)"
    src="$(grep -oP "$pat" webpack.config.js | sed -E "s/.*,\s*['\"]([^'\"]+)['\"].*/\1/" | head -n1 || true)"
    if [[ -n "$src" ]]; then
      FOUND_ANY=1
      echo "└─ $e → $src"
    fi
  done < <(printf "%s\n" "${ENTRIES[@]}")
fi

# b) Common convention: assets/<entry>.js|ts|tsx
for e in "${ENTRIES[@]:-}"; do
  for ext in js ts tsx jsx; do
    cand="assets/${e}.${ext}"
    if [[ -f "$cand" ]]; then
      FOUND_ANY=1
      echo "└─ $e → $cand"
      break
    fi
  done
done

if [[ $FOUND_ANY -eq 0 ]]; then
  echo "└─ (no obvious sources found; check webpack.config.js and /assets)"
fi
hr

# 7) Resolve injected service file paths (Reflection)
echo "🧱 Files for injected services"
php -r '
  require __DIR__."/vendor/autoload.php";
  $class = $argv[1];
  $rc = new ReflectionClass($class);
  $ctor = $rc->getConstructor();
  if (!$ctor) { echo "└─ (no constructor)\n"; exit; }
  foreach ($ctor->getParameters() as $p) {
    $t = $p->getType();
    if (!$t || $t->isBuiltin()) continue;
    $name = $t instanceof ReflectionNamedType ? $t->getName() : (string)$t;
    if (!class_exists($name) && !interface_exists($name)) {
      printf("└─ %s : (not autoloadable)\n", $name);
      continue;
    }
    $rc2 = new ReflectionClass($name);
    printf("└─ %s\n", $rc2->getFileName() ?: "(internal)");
  }
' "$CLASS"
hr

# 8) Quick reminder for exhaustive runtime tracking (optional)
cat <<'TIP'
💡 Tip: For a truly exhaustive runtime list (all files PHP opens), you can trace a dev server request:
  symfony server:start -d
  # Then hit the page in your browser
  strace -qq -f -e trace=openat -p $(pgrep -nf "php-fpm|php.*symfony") 2>&1 | grep -E "\.(php|twig|js|css|json|yml|yaml)$" | sort -u

Done.
TIP
