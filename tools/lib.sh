#!/usr/bin/env bash

set -euo pipefail

# Shared helper functions for Intercept's monorepo split-package tooling.
#
# This file is sourced by the scripts in tools/. It centralises package
# discovery, repository naming, Composer metadata access, GitHub mirror URLs,
# version helpers, and changed-package detection.
#
# Environment variables:
# - ORG: GitHub organisation name. Defaults to "promptphp".
# - ROOT_REPO: Source monorepo name. Defaults to "intercept".
# - DEFAULT_BRANCH: Target branch for mirror repositories. Defaults to "main".
# - PACKAGE_PREFIX: Fallback prefix for generated mirror repo names.
# - DOCS_URL: Default GitHub homepage URL for mirror repositories.
# - MIRROR_TOKEN: Optional token used for HTTPS mirror pushes.

ORG="${ORG:-promptphp}"
ROOT_REPO="${ROOT_REPO:-intercept}"
DEFAULT_BRANCH="${DEFAULT_BRANCH:-main}"
PACKAGE_PREFIX="${PACKAGE_PREFIX:-intercept-}"
DOCS_URL="${DOCS_URL:-https://intercept.promptphp.com}"

repo_root() {
    git rev-parse --show-toplevel
}

root_path() {
    local root
    root="$(repo_root)"

    echo "${root}"
}

require_command() {
    local command="$1"

    if ! command -v "${command}" >/dev/null 2>&1; then
        echo "Missing required command: ${command}" >&2
        exit 1
    fi
}

require_jq() {
    require_command jq
}

require_gh() {
    require_command gh
}

package_folders() {
    local root
    root="$(root_path)"

    find "${root}/src" \
        -mindepth 1 \
        -maxdepth 1 \
        -type d \
        -print \
        | while read -r folder; do
            if [[ -f "${folder}/composer.json" ]]; then
                basename "${folder}"
            fi
        done \
        | sort
}

kebab_case() {
    echo "$1" \
        | sed -E 's/([a-z0-9])([A-Z])/\1-\2/g' \
        | sed -E 's/([A-Z]+)([A-Z][a-z])/\1-\2/g' \
        | tr '[:upper:]' '[:lower:]' \
        | tr '_' '-'
}

package_repo_name() {
    local folder="$1"
    local root
    local override

    root="$(root_path)"

    if [[ -f "${root}/split-overrides.json" ]]; then
        override="$(jq -r --arg folder "${folder}" '.[$folder] // empty' "${root}/split-overrides.json")"

        if [[ -n "${override}" ]]; then
            echo "${override}"
            return
        fi
    fi

    echo "${PACKAGE_PREFIX}$(kebab_case "${folder}")"
}

package_path() {
    local folder="$1"
    local root

    root="$(root_path)"

    echo "${root}/src/${folder}"
}

package_composer_name() {
    local folder="$1"

    jq -r '.name' "$(package_path "${folder}")/composer.json"
}

package_description() {
    local folder="$1"

    jq -r '.description // ""' "$(package_path "${folder}")/composer.json"
}

# Resolve the homepage URL used for GitHub mirror repository metadata.
#
# Individual package composer files may define their own homepage. If they do
# not, split repositories point to the shared Intercept documentation site.
package_homepage() {
    local folder="$1"

    jq -r --arg fallback "${DOCS_URL}" '.homepage // $fallback' "$(package_path "${folder}")/composer.json"
}

package_keywords() {
    local folder="$1"

    jq -r '.keywords // [] | .[]' "$(package_path "${folder}")/composer.json" 2>/dev/null || true
}

mirror_url() {
    local repo="$1"

    if [[ -n "${MIRROR_TOKEN:-}" ]]; then
        echo "https://x-access-token:${MIRROR_TOKEN}@github.com/${ORG}/${repo}.git"
        return
    fi

    echo "git@github.com:${ORG}/${repo}.git"
}

changed_package_folders() {
    local base="${1:-HEAD~1}"
    local root
    root="$(root_path)"

    git -C "${root}" diff --name-only "${base}" HEAD \
        | awk -F/ '/^src\/[^/]+\// { print $2 }' \
        | sort -u \
        | while read -r folder; do
            if [[ -f "${root}/src/${folder}/composer.json" ]]; then
                echo "${folder}"
            fi
        done
}

latest_semver_tag() {
    local repo="$1"
    local url

    url="$(mirror_url "${repo}")"

    git ls-remote --tags "${url}" \
        | awk '{ print $2 }' \
        | sed 's#refs/tags/##' \
        | grep -E '^v?[0-9]+\.[0-9]+\.[0-9]+$' \
        | sed 's/^v//' \
        | sort -V \
        | tail -n 1
}

bump_version() {
    local current="$1"
    local type="$2"

    local major minor patch

    if [[ -z "${current}" ]]; then
        current="0.0.0"
    fi

    IFS='.' read -r major minor patch <<< "${current}"

    case "${type}" in
        major)
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        minor)
            minor=$((minor + 1))
            patch=0
            ;;
        patch)
            patch=$((patch + 1))
            ;;
        *)
            echo "Unsupported release type: ${type}" >&2
            exit 1
            ;;
    esac

    echo "${major}.${minor}.${patch}"
}

ensure_clean_worktree() {
    local root
    root="$(root_path)"

    if [[ -n "$(git -C "${root}" status --porcelain)" ]]; then
        echo "Working tree is not clean. Commit or stash your changes before running this command." >&2
        exit 1
    fi
}