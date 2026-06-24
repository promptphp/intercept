#!/usr/bin/env bash

set -euo pipefail

# Create a GitHub release for the source monorepo.
#
# Split package releases are handled by tools/release.sh. This script creates
# the matching project-level release on the main Intercept repository so users
# landing on promptphp/intercept can see the current public release.
#
# Environment variables:
# - RELEASE_TYPE: Version bump to use when RELEASE_VERSION is not set.
# - RELEASE_VERSION: Optional explicit version, for example "0.1.0" or "v0.1.0".

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=tools/lib.sh
source "${SCRIPT_DIR}/lib.sh"

release_type="${RELEASE_TYPE:-patch}"
release_version="${RELEASE_VERSION:-}"

if [[ -z "${release_version}" ]]; then
    latest_tag="$(git tag --list 'v[0-9]*.[0-9]*.[0-9]*' --sort=-v:refname | head -n 1 || true)"
    release_version="$(bump_version "${latest_tag}" "${release_type}")"
fi

release_version="${release_version#v}"
tag="v${release_version}"

ensure_clean_worktree

if git rev-parse "${tag}" >/dev/null 2>&1; then
    echo "Tag ${tag} already exists locally."
else
    git tag "${tag}"
fi

if git ls-remote --tags origin "refs/tags/${tag}" | grep -q "${tag}"; then
    echo "Tag ${tag} already exists on origin."
else
    git push origin "${tag}"
fi

if gh release view "${tag}" --repo "${ORG}/${ROOT_REPO}" >/dev/null 2>&1; then
    echo "Release ${ORG}/${ROOT_REPO}@${tag} already exists."
    exit 0
fi

gh release create "${tag}" \
    --repo "${ORG}/${ROOT_REPO}" \
    --title "${tag}" \
    --notes "Project release for Intercept ${tag}.

Packages released from this monorepo are available as split Composer packages."

echo "Released ${ORG}/${ROOT_REPO}@${tag}"