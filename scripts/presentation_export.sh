#!/usr/bin/env bash
set -euo pipefail

PYTHON_BIN="${PPTX_PYTHON:-/usr/bin/python3}"
PYTHON_PATHS="${PPTX_PYTHONPATH:-/usr/lib/python3.9/site-packages:/usr/local/lib/python3.9/site-packages}"
SCRIPT_PATH="${PPTX_SCRIPT:-/var/www/html/repweb/scripts/presentation_export.py}"
INPUT_PATH="${PPTX_INPUT:?Missing PPTX_INPUT}"
OUTPUT_PATH="${PPTX_OUTPUT:?Missing PPTX_OUTPUT}"

export PYTHONPATH="${PYTHON_PATHS}"
"${PYTHON_BIN}" "${SCRIPT_PATH}" "${INPUT_PATH}" "${OUTPUT_PATH}"
