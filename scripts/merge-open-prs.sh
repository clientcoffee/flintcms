#!/usr/bin/env bash
set -euo pipefail

remote="${REMOTE:-origin}"
base_branch="${BASE_BRANCH:-main}"
limit="${LIMIT:-200}"
merge_method="${MERGE_METHOD:---merge}"

if ! command -v gh >/dev/null 2>&1; then
  echo "gh is required but not found in PATH." >&2
  exit 1
fi

if ! command -v git >/dev/null 2>&1; then
  echo "git is required but not found in PATH." >&2
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  echo "Working tree is dirty. Commit or stash changes before running." >&2
  exit 1
fi

git fetch "$remote"
git checkout "$base_branch"
git pull --ff-only "$remote" "$base_branch"

mapfile -t pr_lines < <(
  gh pr list \
    --state open \
    --limit "$limit" \
    --json number,headRefName,baseRefName \
    -q '.[] | "\(.number)\t\(.headRefName)\t\(.baseRefName)"' \
    | sort -n
)

if [[ ${#pr_lines[@]} -eq 0 ]]; then
  echo "No open PRs found."
  exit 0
fi

prs=()
branches=()
skipped=()

for line in "${pr_lines[@]}"; do
  IFS=$'\t' read -r number head base <<<"$line"
  if [[ "$base" == "$base_branch" ]]; then
    prs+=("$number")
    branches+=("$head")
  else
    skipped+=("#${number} (${head} -> ${base})")
  fi
done

if [[ ${#prs[@]} -eq 0 ]]; then
  echo "No open PRs targeting ${base_branch}."
  if [[ ${#skipped[@]} -gt 0 ]]; then
    echo "Skipped: ${skipped[*]}"
  fi
  exit 0
fi

if [[ ${#skipped[@]} -gt 0 ]]; then
  echo "Skipping PRs not targeting ${base_branch}: ${skipped[*]}"
fi

echo "Merging PRs targeting ${base_branch}: ${prs[*]}"

first_pr="${prs[0]}"
gh pr merge "$first_pr" "$merge_method"

git checkout "$base_branch"
git pull --ff-only "$remote" "$base_branch"

if [[ ${#prs[@]} -gt 1 ]]; then
  for index in "${!prs[@]}"; do
    if [[ "$index" -eq 0 ]]; then
      continue
    fi

    branch="${branches[$index]}"
    git checkout "$branch"
    git pull --ff-only "$remote" "$branch"
    if ! git merge --no-edit "${remote}/${base_branch}"; then
      echo "Merge conflict while updating ${branch}. Resolve and re-run." >&2
      exit 1
    fi
    git push "$remote" "$branch"
  done

  for index in "${!prs[@]}"; do
    if [[ "$index" -eq 0 ]]; then
      continue
    fi
    gh pr merge "${prs[$index]}" "$merge_method"
  done
fi

git checkout "$base_branch"
git pull --ff-only "$remote" "$base_branch"
