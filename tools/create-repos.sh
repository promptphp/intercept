#!/usr/bin/env bash

set -euo pipefail

# Create missing GitHub mirror repositories for Intercept split packages.
#
# The monorepo remains the source of truth. Mirror repositories are read-only
# package repositories generated from src/* folders using git subtree split.
# This script creates any missing mirrors using the GitHub CLI.
#
# Run with:
# composer mirrors:create

source "$(dirname "$0")/lib.sh"

require_jq
require_gh

for folder in $(package_folders); do
    repo="$(package_repo_name "${folder}")"
    composer_name="$(package_composer_name "${folder}")"
    description="$(package_description "${folder}")"

    if [[ -z "${description}" ]]; then
        description="Split package for ${composer_name}."
    fi

    read_only_description="[READ ONLY] ${description}"

    echo ""
    echo "Checking ${ORG}/${repo}..."

    if gh repo view "${ORG}/${repo}" >/dev/null 2>&1; then
        echo "Repository already exists: ${ORG}/${repo}"
        continue
    fi

    echo "Creating ${ORG}/${repo}..."

    gh repo create "${ORG}/${repo}" \
        --public \
        --description "${read_only_description}" \
        --homepage "https://intercept.promptphp.com" \
        --disable-issues \
        --disable-wiki

    echo "Created ${ORG}/${repo}"
done