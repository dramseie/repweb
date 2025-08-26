#!/bin/bash

set -e

echo "Patching Symfony scaffold files..."

# 1. Fix namespace declaration in ChartController
sed -i '1{/^$/d}' src/Controller/ChartController.php

# 2. Fix invalid string interpolation in DataTableController (filename)
sed -i 's/filename="\$fn"/filename="\'''"'"'\$fn'"'"'"'"/' src/Controller/DataTableController.php
# OR better: replace whole line with correct version
sed -i 's/\(Content-Disposition\).*filename=.*$/\1\', \'attachment; filename="\' . $fn . \'"\'\);/' src/Controller/DataTableController.php

# 3. Fix Python-like expression in GrafanaController embed method
file=src/Controller/GrafanaController.php
start_line=$(grep -n "new Response(\$r->getContent" "$file" | cut -d: -f1)
if [ -n "$start_line" ]; then
    end_line=$((start_line + 2))
    sed -i "${start_line},${end_line}d" "$file"

    cat <<'EOF' | sed "s/^/    /" | sed "${start_line}r /dev/stdin" -i "$file"
\$contentType = 'text/html';
\$headers = \$r->getHeaders(false);
if (isset(\$headers['content-type'][0])) {
    \$contentType = \$headers['content-type'][0];
}
return new Response(
    \$r->getContent(false),
    \$r->getStatusCode(),
    ['Content-Type' => \$contentType]
);
EOF
fi

echo "Patch complete."
