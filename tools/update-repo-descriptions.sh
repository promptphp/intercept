#!/usr/bin/env bash

set -euo pipefail

# Sync GitHub mirror repository metadata.
#
# This script updates each mirror repository's description, homepage URL, and
# topics from package metadata. The homepage defaults to the shared Intercept
# documentation site unless a package composer file defines its own homepage.
#
# Run with:
# composer mirrors:sync

source "$(dirname "$0")/lib.sh"

require_jq
require_gh

for folder in $(package_folders); do
    repo="$(package_repo_name "${folder}")"
    composer_name="$(package_composer_name "${folder}")"
    description="$(package_description "${folder}")"
    homepage="$(package_homepage "${folder}")"

    if [[ -z "${description}" ]]; then
        description="Split package for ${composer_name}."
    fi

    read_only_description="[READ ONLY] ${description}"

    echo ""
    echo "Updating metadata for ${ORG}/${repo}..."

    gh repo edit "${ORG}/${repo}" \
        --description "${read_only_description}" \
        --homepage "${homepage}"

    topics=(
        "promptphp"
        "intercept"
        "laravel"
        "ai"
        "middleware"
    )

    while read -r keyword; do
        if [[ -n "${keyword}" ]]; then
            topics+=("${keyword}")
        fi
    done < <(package_keywords "${folder}")

    for topic in "${topics[@]}"; do
        topic="$(echo "${topic}" | tr '[:upper:]' '[:lower:]' | tr '_' '-')"

        if [[ -n "${topic}" ]]; then
            gh repo edit "${ORG}/${repo}" --add-topic "${topic}" >/dev/null || true
        fi
    done

    echo "Updated ${ORG}/${repo}"
done