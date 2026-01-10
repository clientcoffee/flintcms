#!/usr/bin/env bash
set -euo pipefail

ROOT_OVERRIDE=""

while [[ $# -gt 0 ]]; do
  case $1 in
    --root)
      ROOT_OVERRIDE="${2:-}"
      shift 2
      ;;
    --help|-h)
      echo "Usage: scripts/seed-content.sh [--root <path>]"
      echo ""
      echo "Seeds default markdown pages (when empty) and blocks (missing-only)."
      echo "Defaults to the repository root."
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      echo "Run with --help for usage information"
      exit 1
      ;;
  esac
done

workspace_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
root="${ROOT_OVERRIDE:-$workspace_root}"

app_root="${root}/app"
site_root="${root}/site"
seed_root="${app_root}/core/content"

pages_seed="${seed_root}/pages"
blocks_seed="${seed_root}/blocks"

pages_dest="${site_root}/pages"
blocks_dest="${site_root}/blocks"

has_markdown() {
  local dir="$1"
  if [[ ! -d "${dir}" ]]; then
    return 1
  fi

  if find "${dir}" -type f \( -name "*.md" -o -name "*.mdx" \) -print -quit | grep -q .; then
    return 0
  fi

  return 1
}

copy_missing() {
  local source="$1"
  local destination="$2"

  if [[ ! -d "${source}" ]]; then
    return 0
  fi

  mkdir -p "${destination}"
  rsync -a --ignore-existing "${source}/" "${destination}/" 2>&1 | grep -v "^$" || true
}

if [[ ! -d "${seed_root}" ]]; then
  echo "Seed content not found at ${seed_root}."
  exit 0
fi

if has_markdown "${pages_dest}"; then
  echo "Seed pages skipped (site/pages already has content)."
else
  copy_missing "${pages_seed}" "${pages_dest}"
  echo "Seed pages copied into site/pages."
fi

copy_missing "${blocks_seed}" "${blocks_dest}"
