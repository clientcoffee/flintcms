#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./_shared.sh
source "${script_dir}/_shared.sh"

workspace_root="$(cd "${script_dir}/.." && pwd)"
cd "${workspace_root}"

BUILD_ARGS=()
PORT=8000

while [[ $# -gt 0 ]]; do
  case $1 in
    --port)
      PORT="${2:-}"
      shift 2
      ;;
    --skip-checks|--verbose|-v)
      BUILD_ARGS+=("$1")
      shift
      ;;
    *)
      BUILD_ARGS+=("$1")
      shift
      ;;
  esac
done

ui_banner "Flint build + serve"
ui_step "Build"
./scripts/build.sh "${BUILD_ARGS[@]}"

if [[ -x "./scripts/seed-content.sh" ]]; then
  ui_step "Seed content (if needed)"
  ./scripts/seed-content.sh --root "${workspace_root}/dist"
fi

ui_step "Serve"
ui_note "http://localhost:${PORT} (Ctrl+C to stop)"
cd dist/app

# shellcheck disable=SC2086
php -S "localhost:${PORT}"
