#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./_shared.sh
source "${script_dir}/_shared.sh"

workspace_root="$(cd "${script_dir}/.." && pwd)"
dist_root="${workspace_root}/dist"
vendor_root="${workspace_root}/vendor"

CLEAN_DIST=false
CLEAN_VENDOR=false
CLEAN_RUNTIME=false
runtime_roots=()

usage() {
  echo "Usage: scripts/clean.sh [OPTIONS]"
  echo ""
  echo "Options:"
  echo "  --dist          Remove dist/ output"
  echo "  --vendor        Remove vendor/ dependencies"
  echo "  --runtime       Remove runtime cache artifacts (magic-link)"
  echo "  --root <path>   Root to clean runtime artifacts (repeatable)"
  echo "  --help, -h      Show this help message"
}

while [[ $# -gt 0 ]]; do
  case $1 in
    --dist)
      CLEAN_DIST=true
      shift
      ;;
    --vendor)
      CLEAN_VENDOR=true
      shift
      ;;
    --runtime)
      CLEAN_RUNTIME=true
      shift
      ;;
    --root)
      runtime_roots+=("${2:-}")
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      ui_error "Unknown option: $1"
      ui_note "Run with --help for usage."
      exit 1
      ;;
  esac
done

if [[ "${CLEAN_DIST}" == "false" && "${CLEAN_VENDOR}" == "false" && "${CLEAN_RUNTIME}" == "false" ]]; then
  CLEAN_DIST=true
  CLEAN_VENDOR=true
  CLEAN_RUNTIME=true
fi

if [[ "${CLEAN_DIST}" == "true" && -d "${dist_root}" ]]; then
  ui_step "Remove dist/"
  rm -rf "${dist_root}"
fi

if [[ "${CLEAN_VENDOR}" == "true" && -d "${vendor_root}" ]]; then
  ui_step "Remove vendor/"
  rm -rf "${vendor_root}"
fi

clean_magic_link_artifacts() {
  local root="$1"
  local submissions_dir="${root}/site/submissions"
  local token_path="${root}/app/storage/.tokens/magic-link.json"

  if [[ -d "${submissions_dir}" ]]; then
    shopt -s nullglob
    local files=("${submissions_dir}"/.magic-link-*.json)
    shopt -u nullglob

    if (( ${#files[@]} )); then
      ui_step "Remove magic link cache files in ${submissions_dir}"
      rm -f "${files[@]}"
    fi
  fi

  if [[ -f "${token_path}" ]]; then
    ui_step "Remove magic link token store in ${token_path}"
    rm -f "${token_path}"
  fi
}

if [[ "${CLEAN_RUNTIME}" == "true" ]]; then
  if (( ${#runtime_roots[@]} == 0 )); then
    runtime_roots=("${workspace_root}")
  fi

  for root in "${runtime_roots[@]}"; do
    if [[ -z "${root}" ]]; then
      continue
    fi
    clean_magic_link_artifacts "${root}"
  done
fi
