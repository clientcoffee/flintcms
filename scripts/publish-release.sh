#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: scripts/publish-release.sh <version-tag>"
  exit 1
fi

version_tag="$1"
workspace_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
dist_root="${workspace_root}/dist"
worktree_root="${workspace_root}/.release-main"

if [[ ! -d "${dist_root}" ]]; then
  echo "Missing dist directory. Run scripts/build.sh first."
  exit 1
fi

if git -C "${workspace_root}" worktree list | grep -q "${worktree_root}"; then
  echo "Worktree already exists: ${worktree_root}"
else
  git -C "${workspace_root}" worktree add "${worktree_root}" main
fi

rsync -a --delete "${dist_root}/" "${worktree_root}/"

git -C "${worktree_root}" add -A
if git -C "${worktree_root}" diff --cached --quiet; then
  echo "No changes to publish."
else
  git -C "${worktree_root}" commit -m "Release ${version_tag}"
fi

git -C "${worktree_root}" tag -f "${version_tag}"
git -C "${worktree_root}" push origin main --tags

echo "Published ${version_tag} to main."
