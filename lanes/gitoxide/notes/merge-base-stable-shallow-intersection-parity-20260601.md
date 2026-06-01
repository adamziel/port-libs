# Gitoxide Merge-Base Stable Shallow Intersection Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T084859Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `paint_down_to_common()` and `remove_redundant()` only add a parent to the
  walk queue when `Graph::get_or_insert_full_commit()` returns a commit.
- Inspected `gix-revwalk/src/graph/mod.rs`, where missing commits return
  `Ok(None)` and are skipped, which is the shallow/promisor repository
  behavior this PHP stable helper now mirrors.

## Native PHP Delta

- `MergeBaseFinder::ancestorsWithDistance()` now checks each parent with
  `tryCommit()` before recording it as reachable.
- Missing shallow/promisor parents are no longer pinned into the stable
  `mergeBasesMany()` ancestor intersection, so the nearest present shared
  release baseline survives redundant-base pruning.
- `examples/wordpress-merge-base.php` now exposes the shallow two-head stable
  intersection path for plugin/theme deployment reviews.

## Red-First Evidence

- Before the source change, this command failed with
  `RuntimeException: Missing commit object: e0e0...`:
  `php -r 'require "tools/bootstrap.php"; $fixture=require "lanes/gitoxide/fixtures/wordpress-merge-base.php"; $finder=new PortLibs\Gitoxide\MergeBaseFinder(static fn(string $oid): ?PortLibs\Gitoxide\Commit => $fixture["commits"][$oid] ?? null, useCommitGraphGenerations:false); var_export($finder->mergeBasesMany([$fixture["shallowPluginReview"], $fixture["shallowThemeReview"]])); echo "\n";'`

## Verification

- Focused merge-base test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 421 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 8330 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP commit
fixtures, nullable commit-reader boundary, object-id validation, and
merge-base graph-walk helpers.

## Non-Overlap

This does not repeat accepted first-vs-others graph walking, stale-queue
stopping, missing active commit skipping, graph reuse after hydration,
generation hydration, commitgraph bounds, redundant-prune generation bounds,
walk-start de-duplication, timestamp/permutation baselines, octopus ordering,
transport, pack, reference, sparse-checkout, pathspec, object database, URL,
or tree-merge slices. It is bounded to applying Gitoxide's missing-parent skip
semantics to the PHP stable all-head ancestor intersection helper.
