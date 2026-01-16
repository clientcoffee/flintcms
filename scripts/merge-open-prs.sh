#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./_shared.sh
source "${script_dir}/_shared.sh"

remote="${REMOTE:-origin}"
base_branch="${BASE_BRANCH:-main}"
limit="${LIMIT:-200}"
merge_method="${MERGE_METHOD:---merge}"

if ! command -v gh >/dev/null 2>&1; then
  ui_error "gh is required but not found in PATH."
  exit 1
fi

if ! command -v git >/dev/null 2>&1; then
  ui_error "git is required but not found in PATH."
  exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
  ui_error "Working tree is dirty. Commit or stash changes before running."
  exit 1
fi

ui_banner "Merge open PRs"
run_with_spinner "Fetch ${remote}" git fetch "$remote"
run_with_spinner "Checkout ${base_branch}" git checkout "$base_branch"
run_with_spinner "Update ${base_branch}" git pull --ff-only "$remote" "$base_branch"

mapfile -t pr_lines < <(
  gh pr list \
    --state open \
    --limit "$limit" \
    --json number,headRefName,baseRefName \
    -q '.[] | "\(.number)\t\(.headRefName)\t\(.baseRefName)"' \
    | sort -n
)

if [[ ${#pr_lines[@]} -eq 0 ]]; then
  ui_note "No open PRs found."
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
  ui_note "No open PRs targeting ${base_branch}."
  if [[ ${#skipped[@]} -gt 0 ]]; then
    ui_note "Skipped: ${skipped[*]}"
  fi
  exit 0
fi

if [[ ${#skipped[@]} -gt 0 ]]; then
  ui_note "Skipping PRs not targeting ${base_branch}: ${skipped[*]}"
fi

ui_step "Merging into ${base_branch}: ${prs[*]}"

first_pr="${prs[0]}"
run_with_spinner "Merge PR #${first_pr}" gh pr merge "$first_pr" "$merge_method"

run_with_spinner "Sync ${base_branch}" git checkout "$base_branch"
run_with_spinner "Pull ${base_branch}" git pull --ff-only "$remote" "$base_branch"

if [[ ${#prs[@]} -gt 1 ]]; then
  for index in "${!prs[@]}"; do
    if [[ "$index" -eq 0 ]]; then
      continue
    fi

    branch="${branches[$index]}"
    git checkout "$branch"
    git pull --ff-only "$remote" "$branch"
    if ! git merge --no-edit "${remote}/${base_branch}"; then
      ui_error "Merge conflict while updating ${branch}. Resolve and re-run."
      exit 1
    fi
    git push "$remote" "$branch"
  done

  for index in "${!prs[@]}"; do
    if [[ "$index" -eq 0 ]]; then
      continue
    fi
    run_with_spinner "Merge PR #${prs[$index]}" gh pr merge "${prs[$index]}" "$merge_method"
  done
fi

run_with_spinner "Final sync ${base_branch}" git checkout "$base_branch"
run_with_spinner "Pull ${base_branch}" git pull --ff-only "$remote" "$base_branch"

ui_success "PR merge run complete."
