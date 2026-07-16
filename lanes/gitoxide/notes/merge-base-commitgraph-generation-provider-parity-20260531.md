# Gitoxide Merge-Base Commitgraph Generation Provider Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T212149Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `GenThenTime::from()` uses a commit's existing generation plus commit time
  when painting the graph.
- Inspected `gix-revwalk/src/graph/commit.rs`, where
  `generation_and_timestamp()` can read generation metadata from a commit graph
  without deriving it by recursively inflating every ancestor object.
- Inspected `gix/src/repository/revision.rs`, where repository merge-base
  helpers build graph walks with `commit_graph_if_enabled()`.

## Native PHP Delta

- `MergeBaseFinder` now accepts an optional commit-graph generation provider.
- When the provider returns a non-negative integer generation, graph-walk
  priority uses that value instead of recursively computing generation from
  parent commits.
- The recursive fallback remains unchanged for callers that do not have
  commit-graph metadata.
- The WordPress merge-base fixture/example now models a shallow deployment
  review history with commit-graph generation metadata and confirms the graph
  walk stops at the release baseline without reading the missing grandparent.

## Verification

- Previous focused baseline before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 326 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 337 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `39 test files, 5766 assertions, 0 failures`.
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
fixtures, `Commit` timestamp parsing, object-id validation, and the existing
merge-base graph walker while adding a bounded commit-graph metadata injection
point.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-256 graph
walking, stale-queue stopping in no-commitgraph mode, candidate priority
ordering, generated timestamp-skew baselines, generated permutation baselines,
octopus ordering, protocol, pack, reference, reflog, sparse-checkout,
pathspec, transport, or tree-merge slices. It is bounded to commit-graph
generation metadata during merge-base graph walking.
