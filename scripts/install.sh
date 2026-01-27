#!/usr/bin/env bash
set -euo pipefail

show_help() {
  cat <<'USAGE'
Flint one-line installer

Usage:
  scripts/install.sh [-v <version>] [-d <directory>] [-s <sha256>]

Options:
  -v, --version   Release version (e.g. 0.2.3 or v0.2.3). Default: latest.
  -d, --dest      Destination directory. Default: flint
  -s, --sha256    Expected SHA256 for the release tarball.
  -h, --help      Show this help.
USAGE
}

version="latest"
dest="flint"
expected_sha=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    -v|--version)
      version="${2:-}"
      shift 2
      ;;
    -d|--dest)
      dest="${2:-}"
      shift 2
      ;;
    -s|--sha256)
      expected_sha="${2:-}"
      shift 2
      ;;
    -h|--help)
      show_help
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      show_help
      exit 1
      ;;
  esac
 done

if [[ "$version" == "latest" || -z "$version" ]]; then
  tag="$(curl -fsSL https://api.github.com/repos/clientcoffee/flintcms/releases/latest | grep -m1 '"tag_name":' | sed -E 's/.*"([^"]+)".*/\1/')"
  if [[ -z "$tag" ]]; then
    echo "Unable to determine latest release tag." >&2
    exit 1
  fi
else
  tag="$version"
  if [[ "$tag" != v* ]]; then
    tag="v${tag}"
  fi
fi

archive_url="https://codeload.github.com/clientcoffee/flintcms/tar.gz/refs/tags/${tag}"

if [[ -e "$dest" ]]; then
  echo "Destination '${dest}' already exists." >&2
  exit 1
fi

tmp_dir="$(mktemp -d)"
cleanup() { rm -rf "${tmp_dir}"; }
trap cleanup EXIT
archive_path="${tmp_dir}/flintcms-${tag}.tar.gz"

curl -fsSL "${archive_url}" -o "${archive_path}"

if [[ -n "$expected_sha" ]]; then
  if command -v sha256sum >/dev/null 2>&1; then
    actual_sha="$(sha256sum "${archive_path}" | awk '{print $1}')"
  elif command -v shasum >/dev/null 2>&1; then
    actual_sha="$(shasum -a 256 "${archive_path}" | awk '{print $1}')"
  else
    echo "sha256 tool not found (need sha256sum or shasum)." >&2
    exit 1
  fi

  if [[ "$actual_sha" != "$expected_sha" ]]; then
    echo "SHA256 mismatch. Expected ${expected_sha}, got ${actual_sha}." >&2
    exit 1
  fi
fi

tar -xzf "${archive_path}" -C "${tmp_dir}"
source_dir="${tmp_dir}/flintcms-${tag#v}"
if [[ ! -d "${source_dir}" ]]; then
  echo "Unexpected archive layout." >&2
  exit 1
fi

mv "${source_dir}" "${dest}"

cat <<INSTALL_MSG
Flint ${tag} installed to ${dest}
Next:
  cd ${dest}
  php -S localhost:8000
INSTALL_MSG
