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

echo "Flint test suite"
echo "Running lint, analysis, and unit tests."

require_cmd php
require_cmd composer
require_cmd bun

echo "----------------------------------------"
run_step "PHP lint (phpcs)" composer lint:php
run_step "PHP static analysis (phpstan)" composer analyse
run_step "PHP unit tests (phpunit)" composer test
run_step "JS lint (eslint)" bun run lint:js
echo "----------------------------------------"
echo "ok All checks passed."
