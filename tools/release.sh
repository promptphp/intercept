#!/usr/bin/env bash

set -euo pipefail

# Create Git tags and GitHub releases for split package repositories.
#
# This script reads existing tags from each mirror repository, bumps the next
# semantic version, pushes the tag, and creates a GitHub release. The monorepo
# commit SHA is included in the release notes for traceability.
#
# Run changed packages:
# composer release
#
# Run all packages:
# composer release:all
#
# Example first public release:
# RELEASE_TYPE=minor composer release:all

source "$(dirname "$0")/lib.sh"

require_jq
require_gh
require_command git

all=false
folder_arg=""
release_type="${RELEASE_TYPE:-patch}"
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
        --major)
            release_type="major"
            shift
            ;;
        --minor)
            release_type="minor"
            shift
            ;;
        --patch)
            release_type="patch"
            shift
            ;;
        --type)
            release_type="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

root="$(root_path)"
source_sha="$(git -C "${root}" rev-parse HEAD)"

if [[ "${all}" == true ]]; then
    folders="$(package_folders)"
elif [[ -n "${folder_arg}" ]]; then
    folders="${folder_arg}"
else
    folders="$(changed_package_folders "${base}")"
fi

if [[ -z "${folders}" ]]; then
    echo "No changed packages to release."
    exit 0
fi

for folder in ${folders}; do
    repo="$(package_repo_name "${folder}")"
    composer_name="$(package_composer_name "${folder}")"
    url="$(mirror_url "${repo}")"

    echo ""
    echo "Preparing release for ${composer_name}..."

    latest="$(latest_semver_tag "${repo}" || true)"
    next="$(bump_version "${latest}" "${release_type}")"
    tag="v${next}"

    if gh release view "${tag}" --repo "${ORG}/${repo}" >/dev/null 2>&1; then
        echo "Release ${tag} already exists for ${ORG}/${repo}. Skipping."
        continue
    fi

    tmp="$(mktemp -d)"

    git clone --depth 1 --branch "${DEFAULT_BRANCH}" "${url}" "${tmp}" >/dev/null 2>&1

    git -C "${tmp}" config user.name "${GIT_AUTHOR_NAME:-github-actions[bot]}"
    git -C "${tmp}" config user.email "${GIT_AUTHOR_EMAIL:-github-actions[bot]@users.noreply.github.com}"

    git -C "${tmp}" tag "${tag}"
    git -C "${tmp}" push origin "${tag}"

    release_notes="$(cat <<EOF
Release ${tag} for ${composer_name}.

This package is a read-only subtree split from ${ORG}/${ROOT_REPO}.

intercept-source: ${source_sha}
EOF
)"

    gh release create "${tag}" \
        --repo "${ORG}/${repo}" \
        --title "${composer_name} ${tag}" \
        --notes "${release_notes}"

    rm -rf "${tmp}"

    echo "Released ${ORG}/${repo}@${tag}"
done