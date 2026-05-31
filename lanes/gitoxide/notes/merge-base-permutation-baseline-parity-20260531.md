# Gitoxide Merge-Base Permutation Baseline Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T202758Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, especially
  `merge_base()`, `paint_down_to_common()`, and `remove_redundant()`.
- Inspected `gix-revision/tests/revision/merge_base.rs`, whose `validate()`
  runner checks generated baseline files with and without commit-graph data and
  repeats the checks with a reused graph.
- Read the generated archive
  `gix-revision/tests/fixtures/generated-archives/make_merge_base_repos.tar`
  and ported the `3_permutations.baseline` expectations produced by
  `gix-revision/tests/fixtures/make_merge_base_repos.sh`.

## Native PHP Delta

- `MergeBaseTest.php` now encodes the upstream generated DA/DB/E/D/F/C/B/A/G/H
  graph and all 100 ordered pair expectations from `3_permutations.baseline`.
- Each baseline row checks both `mergeBasesAgainst(first, [other])` and
  `mergeBases(first, other)` in commit-graph and no-commitgraph modes.
- `examples/wordpress-merge-base.php` now exposes the reverse timestamp-skew
  review order to show that WordPress deployment merge-base selection remains
  stable when reviewers provide heads in either order.

## Verification

- Before focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 124 assertions, 0 failures`.
- After focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 326 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `39 test files, 5616 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- PHP lint:
  `php -l lanes/gitoxide/tests/MergeBaseTest.php` and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.

## Dependency Closure

No new support component is needed. This slice reuses existing native PHP
commit fixtures, commit timestamp parsing, object-id validation, and
`MergeBaseFinder` graph-walk behavior.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-256 graph
walking, stale-queue stopping, no-commitgraph priority ordering, generated
timestamp-skew cases, generated three-head union-side cases, octopus ordering,
transport, pack, reference, reflog, sparse-checkout, pathspec, or tree-merge
slices. It is bounded to Gitoxide's generated pairwise permutation baseline
from `make_merge_base_repos.sh`.
