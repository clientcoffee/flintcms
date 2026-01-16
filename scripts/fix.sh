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

ui_banner "Flint code cleanup"
ui_note "Applying auto-fixes for PHP and JavaScript."

require_cmd php
require_cmd composer
require_cmd bun

ui_divider
run_with_spinner "PHP auto-fix (phpcbf)" composer lint:php:fix
run_with_spinner "JS auto-fix (eslint --fix)" bun run lint:js:fix
ui_divider
ui_success "Cleanup complete."
