#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "${ROOT_DIR}"

require_cmd() {
  local cmd="$1"
  if ! command -v "${cmd}" >/dev/null 2>&1; then
    echo "Missing required command: ${cmd}" >&2
    exit 1
  fi
}

run_step() {
  local label="$1"
  shift
  echo "-> ${label}"
  "$@"
}

echo "Flint code cleanup"
echo "Applying auto-fixes for PHP and JavaScript."

require_cmd php
require_cmd composer
require_cmd bun

echo "----------------------------------------"
run_step "PHP auto-fix (phpcbf)" composer lint:php:fix
run_step "JS auto-fix (eslint --fix)" bun run lint:js:fix
echo "----------------------------------------"
echo "ok Cleanup complete."
