#!/usr/bin/env bash
set -euo pipefail

workspace_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="${1:-${workspace_root}/content}"
target_dir="${workspace_root}/site"

if [[ ! -d "${source_dir}" ]]; then
  echo "Source directory not found: ${source_dir}"
  exit 1
fi

echo "Migrating content bundle from ${source_dir} into ${target_dir}"

subdirs=(pages blocks components themes uploads submissions)
for subdir in "${subdirs[@]}"; do
  src="${source_dir}/${subdir}"
  dest="${target_dir}/${subdir}"

  if [[ ! -d "${src}" ]]; then
    continue
  fi

  mkdir -p "${dest}"
  echo "  • Syncing ${subdir}/"
  rsync -a --no-perms "${src}/" "${dest}/"
done

echo "Migration complete. Review ${target_dir} and remove any legacy files you no longer need."
