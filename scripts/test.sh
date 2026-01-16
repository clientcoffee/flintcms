#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=scripts/_shared.sh
source "${ROOT_DIR}/scripts/_shared.sh"

cd "${ROOT_DIR}"

require_cmd() {
  local cmd="$1"
  if ! command -v "${cmd}" >/dev/null 2>&1; then
    ui_error "Missing required command: ${cmd}"
    exit 1
  fi
}

ui_banner "Flint test suite"
ui_note "Running lint, analysis, and unit tests."

require_cmd php
require_cmd composer
require_cmd bun

ui_divider
run_with_spinner "PHP lint (phpcs)" composer lint:php
run_with_spinner "PHP static analysis (phpstan)" composer analyse
run_with_spinner "PHP unit tests (phpunit)" composer test
run_with_spinner "JS lint (eslint)" bun run lint:js
ui_divider
ui_success "All checks passed."
