# Gitoxide Merge-Base Generated Skew Baseline Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T183147Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, especially
  `paint_down_to_common()` and `remove_redundant()`.
- Inspected `gix-revision/tests/revision/merge_base.rs`, whose `validate()`
  test runs every generated `*.baseline` file with and without commit-graph
  cache and then repeats the same expectations using a reused graph.
- Inspected `gix-revision/tests/fixtures/make_merge_base_repos.sh`, focusing
  on generated baseline `2_a` and `4_b`, where deliberately skewed commit
  timestamps must not cause older ancestors to survive redundant-base pruning.

## Native PHP Delta

- `MergeBaseTest.php` now includes static PHP graph fixtures for the upstream
  generated timestamp-skew baselines:
  - `2_a`: `G H` resolves to `B`, not timestamp-newer ancestor `E`.
  - `4_b`: `PL PR` resolves to `C2`, not timestamp-newer root `S`.
- Both fixtures are checked with `useCommitGraphGenerations=true` and
  `false`, mirroring the upstream test's commit-graph/no-commitgraph loop.
- The WordPress merge-base fixture/example now includes a timestamp-skew
  deployment-review graph and records that native merge-base selection prunes
  the newer shared root in favor of the topologically best center baseline.

## Verification

- Before focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 92 assertions, 0 failures`.
- Focused after fixture/test addition:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 110 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `39 test files, 5065 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP commit
fixtures, `Commit` timestamp parsing, and `MergeBaseFinder` graph-walk
behavior.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-256 graph
walking, stale-queue stopping, priority ordering, octopus ordering, transport,
pack, reference, reflog, sparse-checkout, pathspec, or tree-merge slices. It is
bounded to the upstream generated merge-base timestamp-skew pruning baselines.
