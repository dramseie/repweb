#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-$(pwd)}"

mkdir -p "$ROOT/src/Service" "$ROOT/src/Controller" "$ROOT/src/Command" "$ROOT/templates/diag" "$ROOT/config/packages"

# (Paste each file content from this answer into cat EOF blocks if you want fully automated creation.)
echo "👉 Copy the code from the answer into:
  - src/Service/RouteTreeService.php
  - src/Controller/RouteDiagController.php
  - src/Command/CheckRoutesCommand.php
  - templates/diag/routes.html.twig
  - config/packages/route_diag.yaml
Then run: composer require symfony/http-client && php bin/console cache:clear"
