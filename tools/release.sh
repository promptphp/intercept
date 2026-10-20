#!/usr/bin/env bash

set -euo pipefail

# Release every split package and the source monorepo at one version.
#
# All repositories share one version. The script shows the latest tag of each
# repository and asks for the next version. Then it splits every package, tags
# and releases each mirror, and tags and releases the monorepo last.
#
# Run:
# composer release
#
# Skip the prompt, for example in CI:
# RELEASE_VERSION=1.2.0 composer release
#
# The script skips each tag and release that already exists. If a release
# stops partway, run the script again with the same version to complete it.
#
# Environment variables:
# - RELEASE_VERSION: The version to release, for example "1.2.0" or "v1.2.0".
#   The script requires it when no terminal is attached.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=tools/lib.sh
source "${SCRIPT_DIR}/lib.sh"

require_jq
require_gh
require_command git

if [[ $# -gt 0 ]]; then
	echo "release.sh takes no options. Set RELEASE_VERSION to skip the prompt." >&2
	exit 1
fi

root="$(root_path)"
source_sha="$(git -C "${root}" rev-parse HEAD)"
folders="$(package_folders)"
repos="$(for folder in ${folders}; do package_repo_name "${folder}"; done)"
repo_count=$(($(wc -w <<< "${repos}") + 1))

ensure_clean_worktree

remote_sha="$(git -C "${root}" ls-remote origin "refs/heads/${DEFAULT_BRANCH}" | awk '{ print $1 }')"

if [[ "${source_sha}" != "${remote_sha}" ]]; then
	echo "HEAD is not the latest commit on origin/${DEFAULT_BRANCH}. Check out ${DEFAULT_BRANCH}, pull, and try again." >&2
	exit 1
fi

echo "Latest tags:"
echo ""

highest=""

for repo in ${repos} "${ROOT_REPO}"; do
	latest="$(latest_semver_tag "${repo}" || true)"

	printf '  %-40s %s\n' "${ORG}/${repo}" "${latest:+v}${latest:-none}"

	if [[ -n "${latest}" ]]; then
		highest="$(printf '%s\n%s\n' "${highest}" "${latest}" | sed '/^$/d' | sort -V | tail -n 1)"
	fi
done

echo ""

version="${RELEASE_VERSION:-}"

if [[ -z "${version}" ]]; then
	if [[ ! -t 0 ]]; then
		echo "No terminal is attached. Set RELEASE_VERSION, for example RELEASE_VERSION=1.2.0." >&2
		exit 1
	fi

	read -r -p "Version to release (latest is ${highest:+v}${highest:-none}): " version
fi

version="${version#v}"
tag="v${version}"

if [[ ! "${version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Version \"${version}\" is not valid. Use MAJOR.MINOR.PATCH, for example 1.2.0." >&2
	exit 1
fi

if [[ -n "${highest}" ]]; then
	if [[ "$(printf '%s\n%s\n' "${version}" "${highest}" | sort -V | tail -n 1)" != "${version}" ]]; then
		echo "Version ${tag} is lower than the latest tag v${highest}." >&2
		exit 1
	fi

	if [[ "${version}" == "${highest}" ]]; then
		echo "Tag ${tag} already exists. The script adds only the tags and releases that are missing."
	fi
fi

if [[ -t 0 ]]; then
	read -r -p "Release ${tag} to all ${repo_count} repositories? [y/N] " answer

	if [[ ! "${answer}" =~ ^[Yy]$ ]]; then
		echo "Release cancelled."
		exit 0
	fi
fi

# Split every package, so that each mirror tag points at the code of HEAD.
bash "${SCRIPT_DIR}/split.sh" --all

for folder in ${folders}; do
	repo="$(package_repo_name "${folder}")"
	composer_name="$(package_composer_name "${folder}")"
	url="$(mirror_url "${repo}")"

	echo ""
	echo "Release ${tag} for ${composer_name}..."

	if gh release view "${tag}" --repo "${ORG}/${repo}" >/dev/null 2>&1; then
		echo "Release ${tag} already exists for ${ORG}/${repo}. Skipped."
		continue
	fi

	if git ls-remote --tags "${url}" "refs/tags/${tag}" | grep -q "refs/tags/${tag}"; then
		echo "Tag ${tag} already exists for ${ORG}/${repo}."
	else
		tmp="$(mktemp -d)"

		git clone --depth 1 --branch "${DEFAULT_BRANCH}" "${url}" "${tmp}" >/dev/null 2>&1

		git -C "${tmp}" config user.name "${GIT_AUTHOR_NAME:-github-actions[bot]}"
		git -C "${tmp}" config user.email "${GIT_AUTHOR_EMAIL:-github-actions[bot]@users.noreply.github.com}"

		git -C "${tmp}" tag "${tag}"
		git -C "${tmp}" push origin "${tag}"

		rm -rf "${tmp}"
	fi

	release_notes="$(cat <<EOF
Release ${tag} for ${composer_name}.

This package is a read-only subtree split from ${ORG}/${ROOT_REPO}.

intercept-source: ${source_sha}
EOF
)"

	# The pipe to cat gives gh no terminal on stdout, so gh sends no colour
	# query. A late reply to that query prints text such as "11;rgb:..." in the shell.
	gh release create "${tag}" \
		--repo "${ORG}/${repo}" \
		--title "${tag}" \
		--notes "${release_notes}" \
		| cat

	echo "Released ${ORG}/${repo}@${tag}"
done

echo ""
echo "Release ${tag} for ${ORG}/${ROOT_REPO}..."

if git -C "${root}" rev-parse "${tag}" >/dev/null 2>&1; then
	echo "Tag ${tag} already exists locally."
else
	git -C "${root}" tag "${tag}"
fi

if git -C "${root}" ls-remote --tags origin "refs/tags/${tag}" | grep -q "refs/tags/${tag}"; then
	echo "Tag ${tag} already exists on origin."
else
	git -C "${root}" push origin "${tag}"
fi

if gh release view "${tag}" --repo "${ORG}/${ROOT_REPO}" >/dev/null 2>&1; then
	echo "Release ${ORG}/${ROOT_REPO}@${tag} already exists."
else
	# The pipe to cat stops the colour query, as for the mirror releases.
	gh release create "${tag}" \
		--repo "${ORG}/${ROOT_REPO}" \
		--title "${tag}" \
		--notes "Project release for Intercept ${tag}.

Packages released from this monorepo are available as split Composer packages." \
		| cat

	echo "Released ${ORG}/${ROOT_REPO}@${tag}"
fi
