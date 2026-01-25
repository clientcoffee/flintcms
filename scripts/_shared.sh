#!/usr/bin/env bash

# Shared helpers for Flint scripts.

if [[ -n "${FLINT_SCRIPT_SHARED:-}" ]]; then
  return 0
fi
FLINT_SCRIPT_SHARED=1

supports_color() {
  [[ -t 1 && "${TERM:-}" != "dumb" ]]
}

if supports_color; then
  C_RESET=$'\033[0m'
  C_BOLD=$'\033[1m'
  C_DIM=$'\033[2m'
  C_RED=$'\033[31m'
  C_GREEN=$'\033[32m'
  C_YELLOW=$'\033[33m'
  C_BLUE=$'\033[34m'
  C_CYAN=$'\033[36m'
else
  C_RESET=''
  C_BOLD=''
  C_DIM=''
  C_RED=''
  C_GREEN=''
  C_YELLOW=''
  C_BLUE=''
  C_CYAN=''
fi

ui_banner() {
  printf "%b\n" "${C_BOLD}${C_CYAN}$*${C_RESET}"
}

ui_divider() {
  printf "%s\n" "----------------------------------------"
}

ui_step() {
  printf "%b\n" "${C_BLUE}->${C_RESET} $*"
}

ui_warn() {
  printf "%b\n" "${C_YELLOW}!!${C_RESET} $*"
}

ui_error() {
  printf "%b\n" "${C_RED}xx${C_RESET} $*"
}

ui_success() {
  printf "%b\n" "${C_GREEN}ok${C_RESET} $*"
}

ui_note() {
  printf "%b\n" "${C_DIM}$*${C_RESET}"
}

run_with_spinner() {
  local label="$1"
  shift

  if ! supports_color; then
    ui_step "${label}"
    "$@"
    return $?
  fi

  local frames='|/-\\'
  local frame=0
  local tmp
  tmp="$(mktemp 2>/dev/null || mktemp -t flint)"

  "$@" >"${tmp}" 2>&1 &
  local pid=$!

  while kill -0 "${pid}" 2>/dev/null; do
    printf "\r%b %s" "${C_CYAN}${frames:frame:1}${C_RESET}" "${label}"
    frame=$(( (frame + 1) % 4 ))
    sleep 0.08
  done

  wait "${pid}"
  local status=$?
  printf "\r"

  if [[ ${status} -eq 0 ]]; then
    ui_success "${label}"
  else
    ui_error "${label}"
    if [[ -s "${tmp}" ]]; then
      cat "${tmp}" >&2
    fi
  fi

  if [[ "${FLINT_VERBOSE:-}" == "true" && -s "${tmp}" ]]; then
    cat "${tmp}"
  fi

  rm -f "${tmp}"
  return "${status}"
}
