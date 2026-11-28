#!/usr/bin/env bash
# Pin a review to the current working-tree contents without changing the real index.
set -euo pipefail
if [[ $# != 1 ]]; then
    echo "Usage: bash tests/review-snapshot.sh <base-ref>" >&2
    exit 2
fi

cd "$(git rev-parse --show-toplevel)"
base_ref=$(git rev-parse --verify --end-of-options "$1^{commit}")
head=$(git rev-parse HEAD)
base=$(git merge-base "$base_ref" "$head")
scratch=$(mktemp -d)
trap 'rm -f "$scratch/index" "$scratch/index.lock"; rmdir "$scratch"' EXIT
export GIT_INDEX_FILE="$scratch/index"
git read-tree "$head"
git add -A -- .
snapshot=$(git write-tree)

if [[ "$snapshot" == "$(git rev-parse "$base^{tree}")" ]]; then
    echo "The snapshot matches the merge base. No changes to review." >&2
    exit 1
fi

printf 'Merge base: %s\nCommit: %s\nSnapshot tree: %s\n\n' "$base" "$head" "$snapshot"
printf 'Diff: git diff --binary %s %s --\n' "$base" "$snapshot"
printf 'Commits: git log %s..%s --oneline\n' "$base" "$head"
