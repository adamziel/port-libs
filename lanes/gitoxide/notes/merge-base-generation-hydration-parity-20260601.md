# Gitoxide Merge-Base Generation Hydration Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T025423Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revwalk/src/graph/mod.rs`. `Graph::get_or_insert_full_commit()`
  returns `Ok(None)` for a missing commit and does not insert or update graph
  data for that id.
- Inspected `gix-revision/src/merge_base/function.rs`. Reused graph walks clear
  flags but keep reusable complete commit data, so a later lookup can observe a
  parent that was unavailable during an earlier shallow/promisor walk.

## Native PHP Delta

- `MergeBaseFinder` no longer stores computed generation numbers when any
  ancestor lookup is incomplete.
- Complete generation numbers are still cached, preserving the existing
  generation-aware ordering for fully available graphs.
- Added a focused graph-reuse test where a deeper legacy baseline initially has
  a missing parent and sorts behind a newer shallow baseline. After hydrating
  the parent into the same finder, the deeper baseline is recomputed and
  becomes the first merge base.
- Extended the WordPress merge-base fixture/example with the same deployment
  review graph.

## Verification

- Before focused test: `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php`
  => `1 test files, 378 assertions, 0 failures`.
- After focused test: `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php`
  => `1 test files, 388 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests`
  => `40 test files, 7069 assertions, 0 failures`.
- PHP lint passed for `src/MergeBaseFinder.php`, `tests/MergeBaseTest.php`,
  `fixtures/wordpress-merge-base.php`, and
  `examples/wordpress-merge-base.php`.
- Touched example smoke: `php lanes/gitoxide/examples/wordpress-merge-base.php`
  => exit `0`.
- Whitespace: `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP commit
fixtures, the existing merge-base graph walker, and the existing WordPress
merge-base example; no external Git/Rust process or support-library row is
required.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, stale-queue
stopping, commit-graph provider metadata, missing generation infinity,
redundant pruning, timestamp-skew baselines, permutation archive, SHA-256
graph walking, octopus ordering, or missing-commit hydration. It is bounded to
incomplete computed generation reuse after a shallow/promisor parent is
hydrated.
