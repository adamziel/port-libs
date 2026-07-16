# Gitoxide Merge-Base Generation-Infinity Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T233108Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `GenThenTime::from()` uses the commit generation when available and
  `GENERATION_NUMBER_INFINITY` when it is absent.
- Inspected `gix-revwalk/src/graph/commit.rs`, where commits parsed from the
  object database return `None` for generation while still exposing commit time
  and parents.
- Inspected `gix/src/repository/revision.rs`, where repository merge-base
  helpers pass an optional commit-graph cache into reusable revision graphs.

## Native PHP Delta

- `MergeBaseFinder` now distinguishes "no commit-graph provider" from
  "provider exists but has no entry for this commit".
- When the provider returns `null`, merge-base graph-walk priority uses
  `PHP_INT_MAX` as the native generation-infinity sentinel instead of deriving
  a recursive topological generation from parents.
- The existing recursive generation fallback remains for callers that do not
  provide a commit-graph generation callback.
- The WordPress merge-base fixture/example now covers a deployment review graph
  where a commit-graph provider has no metadata entries but the walk still
  stops at the release baseline without inflating the missing grandparent.

## Verification

- Previous focused baseline:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 346 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 357 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 6279 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- Full upstream Cargo workspace runner was not executed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP commit
fixtures, existing `Commit` timestamp parsing, object-id validation, and the
commit-graph generation callback already present in `MergeBaseFinder`.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-256 graph
walking, no-commitgraph priority fallback, stale-queue stopping, explicit
commit-graph metadata reads, redundant-base pruning, generated timestamp-skew
baselines, generated permutation baselines, generated three-head baselines,
octopus ordering, protocol, pack, reference, reflog, sparse-checkout,
pathspec, transport, or tree-merge slices. It is bounded to the upstream
generation-infinity behavior for commits missing commit-graph generation
metadata while a provider/cache is present.
