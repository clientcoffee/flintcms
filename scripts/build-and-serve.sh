#!/usr/bin/env bash
set -euo pipefail

workspace_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
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

echo "Running Flint build..."
./scripts/build.sh "${BUILD_ARGS[@]}"

echo "Serving dist/app on http://localhost:${PORT} (Ctrl+C to stop)"
cd dist/app

# shellcheck disable=SC2086
php -S "localhost:${PORT}"
