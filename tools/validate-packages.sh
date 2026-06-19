#!/usr/bin/env bash

set -euo pipefail

# Validate Intercept package structure and Composer wiring.
#
# This script checks every package under src/* and verifies that it is ready
# for monorepo development and subtree splitting. It catches missing files,
# invalid package names, missing root replace entries, and broken PSR-4
# mappings before split or release scripts are run.
#
# Run with:
# composer packages:validate

source "$(dirname "$0")/lib.sh"

require_jq

root="$(root_path)"
failed=0

fail() {
    echo "✗ $1" >&2
    failed=1
}

pass() {
    echo "✓ $1"
}

for folder in $(package_folders); do
    package_dir="$(package_path "${folder}")"
    repo="$(package_repo_name "${folder}")"
    composer_name="$(package_composer_name "${folder}")"
    expected_name="${ORG}/${repo}"
    namespace="PromptPHP\\Intercept\\${folder}\\"
    test_namespace="PromptPHP\\Intercept\\${folder}\\Tests\\"
    package_src_path="src/${folder}/src/"
    package_tests_path="src/${folder}/tests/"

    echo ""
    echo "Validating ${folder}..."

    [[ -f "${package_dir}/composer.json" ]] \
        && pass "${folder} has composer.json" \
        || fail "${folder} is missing composer.json"

    [[ -f "${package_dir}/README.md" ]] \
        && pass "${folder} has README.md" \
        || fail "${folder} is missing README.md"

    [[ -d "${package_dir}/src" ]] \
        && pass "${folder} has src directory" \
        || fail "${folder} is missing src directory"

    [[ -d "${package_dir}/tests" ]] \
        && pass "${folder} has tests directory" \
        || fail "${folder} is missing tests directory"

    [[ "${composer_name}" == "${expected_name}" ]] \
        && pass "${folder} package name is ${expected_name}" \
        || fail "${folder} package name should be ${expected_name}, found ${composer_name}"

    jq -e --arg name "${composer_name}" '.replace[$name] != null' "${root}/composer.json" >/dev/null \
        && pass "root composer replaces ${composer_name}" \
        || fail "root composer is missing replace entry for ${composer_name}"

    jq -e --arg namespace "${namespace}" --arg path "${package_src_path}" '.autoload["psr-4"][$namespace] == $path' "${root}/composer.json" >/dev/null \
        && pass "root composer autoload maps ${namespace}" \
        || fail "root composer autoload missing ${namespace} => ${package_src_path}"

    jq -e --arg namespace "${test_namespace}" --arg path "${package_tests_path}" '."autoload-dev"."psr-4"[$namespace] == $path' "${root}/composer.json" >/dev/null \
        && pass "root composer autoload-dev maps ${test_namespace}" \
        || fail "root composer autoload-dev missing ${test_namespace} => ${package_tests_path}"

    jq -e --arg namespace "${namespace}" '.autoload["psr-4"][$namespace] == "src/"' "${package_dir}/composer.json" >/dev/null \
        && pass "${folder} composer maps package namespace" \
        || fail "${folder} composer should map ${namespace} => src/"

    jq -e --arg namespace "${test_namespace}" '."autoload-dev"."psr-4"[$namespace] == "tests/"' "${package_dir}/composer.json" >/dev/null \
        && pass "${folder} composer maps test namespace" \
        || fail "${folder} composer should map ${test_namespace} => tests/"
done

echo ""

if [[ "${failed}" -eq 1 ]]; then
    echo "Package validation failed." >&2
    exit 1
fi

echo "All packages are valid."