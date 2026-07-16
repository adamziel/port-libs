# Gitoxide Merge-Base SHA-256 Graph-Walk Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T092056Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs` and
  `gix-revision/src/merge_base/mod.rs`.
- Inspected `gix-revision/tests/revision/merge_base.rs` and
  `gitoxide-core/src/repository/merge_base.rs`.

## Native PHP Delta

- `MergeBaseFinder` now accepts 40-character SHA-1 and 64-character SHA-256
  commit ids for pairwise, graph-walk, and octopus-style merge-base helpers.
- Mixed SHA-1/SHA-256 head ids are rejected before traversal.
- Mixed-format parent links are rejected while walking, preventing a synthetic
  graph from returning a base that could not exist in one Git object-format
  repository.
- The WordPress merge-base fixture and example now include SHA-256 plugin/theme
  review refs that preserve the same release-baseline result as SHA-1 refs.

## Verification

- Red-first focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 32 assertions, 1 failures` at the SHA-1-only guard.
- After focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 49 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `38 test files, 3806 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP commit
fixtures, commit parsing, and object-id validation boundaries in the Gitoxide
lane.

## Non-Overlap

This slice does not repeat protocol v2 SHA-256 `ls-refs` advertisements, pack
delta oversized-header guards, the earlier merge-base `first`/`others`
graph-walk mode, octopus merge-base fixtures, recursive tree multiple-base
fixtures, or transport/ref/object database slices. It is bounded to
object-format parity for merge-base graph traversal.
