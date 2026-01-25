#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./_shared.sh
source "${script_dir}/_shared.sh"

# Parse arguments
SKIP_CHECKS=false
VERBOSE=false

while [[ $# -gt 0 ]]; do
  case $1 in
    --skip-checks)
      SKIP_CHECKS=true
      shift
      ;;
    --verbose|-v)
      VERBOSE=true
      shift
      ;;
    --help|-h)
      echo "Usage: scripts/build.sh [OPTIONS]"
      echo ""
      echo "Options:"
      echo "  --skip-checks    Skip pre-build validation (tests and static analysis)"
      echo "  --verbose, -v    Show detailed output"
      echo "  --help, -h       Show this help message"
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      echo "Run with --help for usage information"
      exit 1
      ;;
  esac
done

workspace_root="$(cd "${script_dir}/.." && pwd)"
app_root="${workspace_root}/app"
site_root="${workspace_root}/site"
dist_root="${workspace_root}/dist"
clean_script="${workspace_root}/scripts/clean.sh"

FLINT_VERBOSE="${VERBOSE}"

ui_banner "    ________  _       __"
ui_banner "   / ____/ / (_)___  / /_"
ui_banner "  / /_  / / / / __ \/ __/"
ui_banner " / __/ / /_/ / / / / /____'\ "
ui_banner "/_/    \______/  \_________/"
ui_note "Flint build. Output: ${dist_root}"
ui_divider

if [[ -x "${clean_script}" ]]; then
  ui_step "Clean build inputs"
  FLINT_VERBOSE="${VERBOSE}" "${clean_script}" --dist --runtime
else
  ui_warn "Clean script missing; removing dist/ manually."
  rm -rf "${dist_root}"
fi

# Pre-build checks
if [[ "${SKIP_CHECKS}" == "false" ]]; then
  ui_step "Pre-build checks"

  # Ensure a clean dependency set before running checks.
  if [[ -x "${clean_script}" ]]; then
    FLINT_VERBOSE="${VERBOSE}" "${clean_script}" --vendor
  elif [[ -d "${workspace_root}/vendor" ]]; then
    ui_step "Clean vendor/"
    rm -rf "${workspace_root}/vendor"
  fi

  if ! run_with_spinner "Install composer dependencies" composer install --working-dir="${workspace_root}" --no-interaction --prefer-dist; then
    ui_error "Composer install failed. Halting build."
    exit 1
  fi

  if ! run_with_spinner "Running tests" composer test --working-dir="${workspace_root}"; then
    ui_error "Tests failed. Halting build."
    exit 1
  fi

  if ! run_with_spinner "Static analysis" composer analyse --working-dir="${workspace_root}"; then
    ui_error "Static analysis reported issues. Halting build."
    exit 1
  fi
else
  ui_warn "Skipping checks (--skip-checks)."
fi

# Prepare build directory (dist/ was already wiped)
ui_step "Preparing dist/"
mkdir -p "${dist_root}"

# Copy app directory
if [[ "${VERBOSE}" == "true" ]]; then
  ui_step "Sync app/ -> dist/app/"
  rsync -av \
    --delete \
    "${app_root}/" \
    "${dist_root}/app/"
else
  run_with_spinner "Sync app/ -> dist/app/" rsync -a --delete "${app_root}/" "${dist_root}/app/"
fi

# Copy index-dist.php to dist root if present
index_dist_src="${app_root}/index-dist.php"
if [[ -f "${index_dist_src}" ]]; then
  ui_step "Copy index-dist.php"
  cp "${index_dist_src}" "${dist_root}/index.php"
fi

# Copy root .htaccess into the build if present
root_htaccess_src="${workspace_root}/.htaccess"
if [[ -f "${root_htaccess_src}" ]]; then
  ui_step "Copy .htaccess"
  cp "${root_htaccess_src}" "${dist_root}/.htaccess"
fi

# Copy site directory
if [[ "${VERBOSE}" == "true" ]]; then
  ui_step "Sync site/ -> dist/site/"
  rsync -av \
    --delete \
    --exclude="uploads/" \
    "${site_root}/" \
    "${dist_root}/site/"
else
  run_with_spinner "Sync site/ -> dist/site/" rsync -a --delete --exclude="uploads/" "${site_root}/" "${dist_root}/site/"
fi

# Seed default pages/blocks into dist/site when empty.
seed_script="${workspace_root}/scripts/seed-content.sh"
if [[ -x "${seed_script}" ]]; then
  ui_step "Seed default content (if needed)"
  "${seed_script}" --root "${dist_root}"
fi

if [[ -x "${clean_script}" ]]; then
  FLINT_VERBOSE="${VERBOSE}" "${clean_script}" --runtime --root "${dist_root}"
fi

# Create empty uploads directory
mkdir -p "${dist_root}/site/uploads"

# Restore minimal uploads guard files from site/ (without copying user uploads)
for upload_stub in ".htaccess" "index.php"; do
  stub_src="${site_root}/uploads/${upload_stub}"
  if [[ -f "${stub_src}" ]]; then
    ui_step "Copy uploads/${upload_stub}"
    cp "${stub_src}" "${dist_root}/site/uploads/${upload_stub}"
  fi
done

# Copy default project imagery from core/content/uploads into the build so themes can reference it.
core_uploads_dir="${app_root}/core/content/uploads"
if [[ -d "${core_uploads_dir}" ]]; then
  run_with_spinner "Copy core uploads" rsync -a "${core_uploads_dir}/" "${dist_root}/site/uploads/"
fi

# Copy site config example into the build
ui_step "Copy site/config.example.php"
cp "${site_root}/config.example.php" "${dist_root}/site/config.example.php"

# Copy LICENSE to root (if exists)
if [[ -f "${workspace_root}/LICENSE" ]]; then
  ui_step "Copy LICENSE"
  cp "${workspace_root}/LICENSE" "${dist_root}/LICENSE"
fi

# Copy README to root (if exists)
if [[ -f "${workspace_root}/README.md" ]]; then
  ui_step "Copy README.md"
  cp "${workspace_root}/README.md" "${dist_root}/README.md"
fi

# Copy UPDATES.md to root (if exists)
if [[ -f "${workspace_root}/UPDATES.md" ]]; then
  ui_step "Copy UPDATES.md"
  cp "${workspace_root}/UPDATES.md" "${dist_root}/UPDATES.md"
fi

# Generate build metadata
ui_step "Write build metadata"
build_date=$(date -u +"%Y-%m-%d %H:%M:%S UTC")
build_hash=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
cat > "${dist_root}/.build-info" <<EOF
Build Date: ${build_date}
Commit: ${build_hash}
Built From: app/ and site/
Build Script: scripts/build.sh
EOF

# Post-build validation
ui_step "Validate build output"
validation_errors=0

# Check for required files
required_files=("app/index.php" "site/config.example.php" "app/core/App.php" "site/themes" "README.md")
for file in "${required_files[@]}"; do
  if [[ ! -e "${dist_root}/${file}" ]]; then
    ui_error "Missing: ${file}"
    ((validation_errors++))
  fi
done

# Check for excluded files that shouldn't be there
excluded_files=("config.ini" "config.example.ini" "composer.json" "phpunit.xml" "tests")
for file in "${excluded_files[@]}"; do
  if [[ -e "${dist_root}/${file}" ]]; then
    ui_error "Excluded file in dist: ${file}"
    ((validation_errors++))
  fi
done

# Check for runtime cache files that should not ship.
shopt -s nullglob
runtime_cache_files=(
  "${dist_root}"/site/submissions/.magic-link-*.json
)
shopt -u nullglob
if (( ${#runtime_cache_files[@]} )); then
  ui_error "Runtime cache files in dist/site/submissions"
  ((validation_errors++))
fi

if [[ -f "${dist_root}/app/storage/.tokens/magic-link.json" ]]; then
  ui_error "Runtime token store in dist/app/storage/.tokens/magic-link.json"
  ((validation_errors++))
fi

if [[ ${validation_errors} -eq 0 ]]; then
  ui_success "Build validation passed"
else
  ui_error "Build validation failed (${validation_errors} errors)"
  exit 1
fi

# Calculate dist size
dist_size=$(du -sh "${dist_root}" | cut -f1)

# Summary
ui_divider
ui_success "Build complete"
ui_note "Size: ${dist_size} | Commit: ${build_hash}"
ui_note "Next: cd dist/app && php -S localhost:8000"
ui_note "Release: scripts/publish-release.sh <version>"
