#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./_shared.sh
source "${script_dir}/_shared.sh"

workspace_root="$(cd "${script_dir}/.." && pwd)"
source_dir="${1:-${workspace_root}/content}"
target_dir="${workspace_root}/site"

if [[ ! -d "${source_dir}" ]]; then
  ui_error "Source directory not found: ${source_dir}"
  exit 1
fi

ui_banner "Content migration"
ui_note "From: ${source_dir}"
ui_note "To:   ${target_dir}"

subdirs=(pages blocks components themes uploads submissions)
for subdir in "${subdirs[@]}"; do
  src="${source_dir}/${subdir}"
  dest="${target_dir}/${subdir}"

  if [[ ! -d "${src}" ]]; then
    continue
  fi

  mkdir -p "${dest}"
  run_with_spinner "Sync ${subdir}/" rsync -a --no-perms "${src}/" "${dest}/"
done

ui_success "Migration complete."
ui_note "Review ${target_dir} and remove any legacy files you no longer need."
