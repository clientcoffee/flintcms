#!/usr/bin/env bash
set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

workspace_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
app_root="${workspace_root}/app"
content_root="${workspace_root}/content"
dist_root="${workspace_root}/dist"
buildignore="${workspace_root}/.buildignore"

echo -e "${BLUE}=== Flint Build Process ===${NC}"
echo ""

# Pre-build checks
if [[ "${SKIP_CHECKS}" == "false" ]]; then
  echo -e "${BLUE}Running pre-build checks...${NC}"

  # Check if composer dependencies are installed
  if [[ ! -d "${workspace_root}/vendor" ]]; then
    echo -e "${YELLOW}Warning: vendor/ not found. Run 'composer install' first.${NC}"
    echo -e "${YELLOW}Skipping tests and analysis...${NC}"
  else
    # Run tests
    echo -e "${BLUE}→ Running tests...${NC}"
    if composer test --working-dir="${workspace_root}" > /dev/null 2>&1; then
      echo -e "${GREEN}✓ Tests passed${NC}"
    else
      echo -e "${RED}✗ Tests failed${NC}"
      echo -e "${YELLOW}Note: Build will continue, but you may want to fix failing tests.${NC}"
    fi

    # Run static analysis
    echo -e "${BLUE}→ Running static analysis...${NC}"
    if composer analyse --working-dir="${workspace_root}" > /dev/null 2>&1; then
      echo -e "${GREEN}✓ Static analysis passed${NC}"
    else
      echo -e "${YELLOW}⚠ Static analysis found issues${NC}"
      echo -e "${YELLOW}Note: Build will continue, but you may want to review the issues.${NC}"
    fi
  fi
  echo ""
else
  echo -e "${YELLOW}Skipping pre-build checks (--skip-checks)${NC}"
  echo ""
fi

# Clean and create dist directory
echo -e "${BLUE}Preparing build directory...${NC}"
rm -rf "${dist_root}"
mkdir -p "${dist_root}"

# Build rsync exclude arguments from .buildignore
rsync_excludes=()
if [[ -f "${buildignore}" ]]; then
  while IFS= read -r line; do
    # Skip empty lines and comments
    [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
    rsync_excludes+=("--exclude=$line")
  done < "${buildignore}"
fi

# Copy app directory
echo -e "${BLUE}Copying app/ to dist/app/...${NC}"
if [[ "${VERBOSE}" == "true" ]]; then
  rsync -av \
    --delete \
    "${rsync_excludes[@]}" \
    "${app_root}/" \
    "${dist_root}/app/"
else
  rsync -a \
    --delete \
    "${rsync_excludes[@]}" \
    "${app_root}/" \
    "${dist_root}/app/" 2>&1 | grep -v "^$" || true
fi

# Copy content directory
echo -e "${BLUE}Copying content/ to dist/content/...${NC}"
if [[ "${VERBOSE}" == "true" ]]; then
  rsync -av \
    --delete \
    --exclude="uploads/" \
    "${content_root}/" \
    "${dist_root}/content/"
else
  rsync -a \
    --delete \
    --exclude="uploads/" \
    "${content_root}/" \
    "${dist_root}/content/" 2>&1 | grep -v "^$" || true
fi

# Create empty uploads directory
mkdir -p "${dist_root}/content/uploads"

# Copy config.example.php to app directory
echo -e "${BLUE}Copying config.example.php...${NC}"
cp "${app_root}/config.example.php" "${dist_root}/app/config.example.php"

# Copy LICENSE to root (if exists)
if [[ -f "${workspace_root}/LICENSE" ]]; then
  echo -e "${BLUE}Copying LICENSE...${NC}"
  cp "${workspace_root}/LICENSE" "${dist_root}/LICENSE"
fi

# Copy UPDATES.md to root (if exists)
if [[ -f "${workspace_root}/UPDATES.md" ]]; then
  echo -e "${BLUE}Copying UPDATES.md...${NC}"
  cp "${workspace_root}/UPDATES.md" "${dist_root}/UPDATES.md"
fi

# Generate build metadata
echo -e "${BLUE}Generating build metadata...${NC}"
build_date=$(date -u +"%Y-%m-%d %H:%M:%S UTC")
build_hash=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
cat > "${dist_root}/.build-info" <<EOF
Build Date: ${build_date}
Commit: ${build_hash}
Built From: app/ and content/
Build Script: scripts/build.sh
EOF

# Post-build validation
echo -e "${BLUE}Validating build...${NC}"
validation_errors=0

# Check for required files
required_files=("app/index.php" "app/config.example.php" "app/core/App.php" "content/themes")
for file in "${required_files[@]}"; do
  if [[ ! -e "${dist_root}/${file}" ]]; then
    echo -e "${RED}✗ Missing required file/directory: ${file}${NC}"
    ((validation_errors++))
  fi
done

# Check for excluded files that shouldn't be there
excluded_files=("config.ini" "config.example.ini" "composer.json" "phpunit.xml" "tests")
for file in "${excluded_files[@]}"; do
  if [[ -e "${dist_root}/${file}" ]]; then
    echo -e "${RED}✗ Excluded file found in dist: ${file}${NC}"
    ((validation_errors++))
  fi
done

if [[ ${validation_errors} -eq 0 ]]; then
  echo -e "${GREEN}✓ Build validation passed${NC}"
else
  echo -e "${RED}✗ Build validation failed with ${validation_errors} error(s)${NC}"
  exit 1
fi

# Calculate dist size
dist_size=$(du -sh "${dist_root}" | cut -f1)

# Summary
echo ""
echo -e "${GREEN}=== Build Complete ===${NC}"
echo -e "Output: ${dist_root}"
echo -e "Size: ${dist_size}"
echo -e "Commit: ${build_hash}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo -e "  • Review dist/ contents"
echo -e "  • Test the build locally: ${BLUE}cd dist/app && php -S localhost:8000${NC}"
echo -e "  • Publish release: ${BLUE}scripts/publish-release.sh <version>${NC}"
echo ""
