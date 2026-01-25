#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./_shared.sh
source "${script_dir}/_shared.sh"

if [[ $# -lt 1 ]]; then
  echo "Usage: scripts/publish-release.sh <version-tag> [source-branch] [release-branch]"
  exit 1
fi

version_tag="$1"
source_branch="${2:-dev}"
release_branch="${3:-main}"
workspace_root="$(cd "${script_dir}/.." && pwd)"
dist_root="${workspace_root}/dist"
worktree_root="${workspace_root}/.release-${release_branch}"

if [[ "${source_branch}" != "dev" ]]; then
  ui_error "Releases must be published from the dev branch."
  ui_note "Usage: scripts/publish-release.sh <version-tag> dev main"
  exit 1
fi

if [[ ! -d "${dist_root}" ]]; then
  ui_error "Missing dist directory. Run scripts/build.sh first."
  exit 1
fi

current_branch="$(git -C "${workspace_root}" rev-parse --abbrev-ref HEAD)"
if [[ "${current_branch}" != "${source_branch}" ]]; then
  ui_error "Current branch is ${current_branch}. Switch to ${source_branch} before releasing."
  exit 1
fi

ui_banner "    ________  _       __"
ui_banner "   / ____/ / (_)___  / /_"
ui_banner "  / /_  / / / / __ \/ __/"
ui_banner " / __/ / /_/ / / / / /____'\ "
ui_banner "/_/    \______/  \_________/"
ui_note "Publish release ${version_tag}"
ui_divider

if git -C "${workspace_root}" worktree list | grep -q "${worktree_root}"; then
  ui_note "Worktree exists: ${worktree_root}"
else
  run_with_spinner "Create worktree ${release_branch}" git -C "${workspace_root}" worktree add "${worktree_root}" "${release_branch}"
fi

run_with_spinner "Sync dist -> ${release_branch}" rsync -a --delete --exclude=".git" "${dist_root}/" "${worktree_root}/"

git -C "${worktree_root}" add -A
if git -C "${worktree_root}" diff --cached --quiet; then
  ui_warn "No changes to publish."
else
  git -C "${worktree_root}" commit -m "Release ${version_tag}"
fi

git -C "${worktree_root}" tag -f "${version_tag}"
git -C "${worktree_root}" push origin "${release_branch}" --tags

ui_success "Published ${version_tag} to ${release_branch}."
