#!/usr/bin/env bash

set -euo pipefail

# Split package directories into their GitHub mirror repositories.
#
# This script uses git subtree split to push each package under src/* to its
# matching read-only mirror repository. It can split all packages, one package,
# or only packages changed since a base commit.
#
# Run changed packages:
# composer split
#
# Run all packages:
# composer split:all

source "$(dirname "$0")/lib.sh"

require_jq
require_command git

all=false
folder_arg=""
base="${BASE_SHA:-HEAD~1}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --all)
            all=true
            shift
            ;;
        --folder)
            folder_arg="$2"
            shift 2
            ;;
        --base)
            base="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

root="$(root_path)"

if [[ "${all}" == true ]]; then
    folders="$(package_folders)"
elif [[ -n "${folder_arg}" ]]; then
    folders="${folder_arg}"
else
    folders="$(changed_package_folders "${base}")"
fi

if [[ -z "${folders}" ]]; then
    echo "No changed packages to split."
    exit 0
fi

for folder in ${folders}; do
    package_dir="$(package_path "${folder}")"

    if [[ ! -f "${package_dir}/composer.json" ]]; then
        echo "Skipping ${folder}; no composer.json found."
        continue
    fi

    repo="$(package_repo_name "${folder}")"
    branch="split/${repo}"
    url="$(mirror_url "${repo}")"

    echo ""
    echo "Splitting ${folder} into ${ORG}/${repo}..."

    git -C "${root}" branch -D "${branch}" >/dev/null 2>&1 || true

    git -C "${root}" subtree split \
        --prefix="src/${folder}" \
        -b "${branch}"

    git -C "${root}" push "${url}" "${branch}:${DEFAULT_BRANCH}" --force

    git -C "${root}" branch -D "${branch}" >/dev/null

    echo "Pushed ${folder} to ${ORG}/${repo}:${DEFAULT_BRANCH}"
done