# Gitoxide Merge-Base Stale-Queue Graph-Walk Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T150946Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, specifically
  `paint_down_to_common()` and the `Flags::STALE` queue stop condition.
- Inspected `gix-revwalk/src/graph/commit.rs`, where no-commitgraph walks use
  parsed commit time and no generation number, so traversal does not need deep
  ancestors below an already stale common base.

## Native PHP Delta

- `MergeBaseFinder::mergeBases()` and `mergeBasesAgainst()` now use a lazy
  paint-down graph walk for pairwise and first-vs-others merge-base discovery.
- The walk marks commits with first-side, other-side, stale, and result flags,
  stops when the remaining queue is stale, and preserves the existing
  deterministic ordering/pruning for multiple independent bases.
- This prevents the no-commitgraph path from enumerating deep ancestors below a
  discovered common base, matching Gitoxide behavior for shallow or partially
  available histories.
- The WordPress merge-base fixture/example now includes a shallow deployment
  history whose release baseline has a stale parent with a missing grandparent;
  graph-walk discovery stops at the release baseline instead of trying to read
  the missing ancestor.

## Verification

- Before focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 83 assertions, 0 failures`.
- After focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 92 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `39 test files, 4749 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- PHP lint:
  `php -l` passed for `src/MergeBaseFinder.php`,
  `tests/MergeBaseTest.php`, `fixtures/wordpress-merge-base.php`, and
  `examples/wordpress-merge-base.php`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP commit
fixtures, commit timestamp parsing, object-id validation, and merge-base graph
helpers.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-256 graph
walking, no-commitgraph priority ordering, octopus ordering, tree merge
multiple-base fixtures, protocol, pack, config, ref, reflog, or transport
clusters. It is bounded to Gitoxide's stale-queue stop behavior during
merge-base graph walking.
