#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: scripts/publish-release.sh <version-tag> [source-branch] [release-branch]"
  exit 1
fi

version_tag="$1"
source_branch="${2:-dev}"
release_branch="${3:-main}"
workspace_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
dist_root="${workspace_root}/dist"
worktree_root="${workspace_root}/.release-${release_branch}"

if [[ ! -d "${dist_root}" ]]; then
  echo "Missing dist directory. Run scripts/build.sh first."
  exit 1
fi

current_branch="$(git -C "${workspace_root}" rev-parse --abbrev-ref HEAD)"
if [[ "${current_branch}" != "${source_branch}" ]]; then
  echo "Current branch is ${current_branch}. Switch to ${source_branch} before releasing."
  exit 1
fi

if git -C "${workspace_root}" worktree list | grep -q "${worktree_root}"; then
  echo "Worktree already exists: ${worktree_root}"
else
  git -C "${workspace_root}" worktree add "${worktree_root}" "${release_branch}"
fi

rsync -a --delete --exclude=".git" "${dist_root}/" "${worktree_root}/"

git -C "${worktree_root}" add -A
if git -C "${worktree_root}" diff --cached --quiet; then
  echo "No changes to publish."
else
  git -C "${worktree_root}" commit -m "Release ${version_tag}"
fi

git -C "${worktree_root}" tag -f "${version_tag}"
git -C "${worktree_root}" push origin "${release_branch}" --tags

echo "Published ${version_tag} to ${release_branch}."
