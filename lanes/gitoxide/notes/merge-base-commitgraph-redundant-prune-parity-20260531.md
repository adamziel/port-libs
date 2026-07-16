# Gitoxide Merge-Base Commitgraph Redundant-Prune Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T222709Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, especially
  `remove_redundant()`, where redundant result bases are pruned by walking
  parents only while their generation is still at or above the lowest live
  result generation.
- Inspected `gix-revwalk/src/graph/commit.rs` and
  `gix/src/repository/revision.rs` for the commit-graph generation metadata
  path used by repository merge-base graph walks.

## Native PHP Delta

- `MergeBaseFinder` now removes redundant pairwise and first-vs-others
  merge-base candidates with a bounded generation-aware walk instead of
  recursively materializing full ancestor sets for every candidate.
- The pruning walk keeps independent result bases, marks candidates reachable
  from another candidate as stale, and stops below the lowest live result
  generation when commit-graph metadata is available.
- The no-commitgraph path keeps generation pruning disabled, matching
  Gitoxide's generation-infinity fallback.
- `wordpress-merge-base.php` now includes a shallow review-branch graph where
  independent legacy/security bases are preserved without reading the missing
  grandparent below a stale legacy parent.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 337 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 346 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `39 test files, 5959 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-merge-base.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` all passed.
- JSON validation passed for `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` and
  `lanes/gitoxide/lane-status.json`.
- `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. The slice reuses native PHP commit
fixtures, `Commit` timestamp parsing, the existing object-id validation
boundary, and the commit-graph generation provider added by the accepted
merge-base metadata slice.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-256 graph
walking, stale-queue stopping for a single base, no-commitgraph priority
ordering, candidate priority ordering, generated timestamp-skew baselines,
generated permutation/three-head baselines, octopus ordering, protocol, pack,
reference, reflog, sparse-checkout, pathspec, transport, or tree-merge slices.
It is bounded to commit-graph-backed redundant-base pruning for multiple
merge-base candidates.
