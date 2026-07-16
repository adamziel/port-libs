# Gitoxide Merge-Base Command Output Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T124110Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `gix_revision::merge_base(first, others)` returns all graph-walk bases from
  best to worst, shortcuts empty `others` to `first`, and returns no value for
  unrelated histories.
- Inspected `gitoxide-core/src/repository/merge_base.rs`, where the plumbing
  command writes each resolved base id on its own line and fails with
  `No base found for {first} and {others}` when the graph walk yields none.

## Native PHP Delta

- Added `MergeBaseCommand::humanOutput()` as a native PHP command-output helper
  over `MergeBaseFinder::mergeBasesAgainst()`.
- The helper preserves upstream human output shape: all graph-walk bases are
  newline-terminated, empty `others` prints `first`, `first` contained in
  `others` prints `first`, and unrelated histories throw a no-base diagnostic.
- Extended the WordPress merge-base example with hotfix review output that
  prints both criss-cross bases and an archived-review no-base error.

## Verification

- Before this slice, focused baseline:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 439 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 446 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseCommand.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`MergeBaseFinder`, commit fixtures, object-id validation, and WordPress
merge-base example; no Git/Rust subprocess or new support-library row is
required.

## Non-Overlap

This does not repeat accepted first-vs-others graph walking, shallow missing
commit handling, stale-queue stopping, graph/generation hydration reuse,
commit-graph bounds, redundant pruning, timestamp/permutation/generated
baselines, SHA-256/object-database graph walking, octopus ordering, transport,
pack, reference, sparse-checkout, pathspec, URL/refspec, partial-clone, or
tree-merge slices. It is bounded to the upstream repository/plumbing human
output behavior layered over existing merge-base graph-walk results.
