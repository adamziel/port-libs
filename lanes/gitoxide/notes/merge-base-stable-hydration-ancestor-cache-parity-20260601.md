# Gitoxide Merge-Base Stable Hydration Ancestor Cache Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T113152Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revwalk/src/graph/mod.rs`.
  `Graph::get_or_insert_full_commit()` returns `Ok(None)` for a missing commit
  without inserting the id into the graph.
- Inspected `gix-revision/src/merge_base/function.rs`, where merge-base graph
  reuse clears flags but keeps successful commit data only, so a later walk can
  observe a shallow/promisor parent hydrated after an earlier miss.

## Native PHP Delta

- `MergeBaseFinder::ancestorsWithDistance()` now caches only complete ancestor
  traversals.
- Stable `mergeBasesMany()` calls still return the best available result while
  a shallow/promisor parent is absent, but incomplete ancestor sets are not
  pinned into the cache.
- The WordPress merge-base example now covers a reused stable all-head
  intersection finder before and after a missing release baseline is hydrated.

## Red-First Evidence

- After adding the focused test and before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 435 assertions, 1 failures`; the hydrated stable intersection
  expected the release baseline and got an empty cached result.

## Verification

- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php && php -l lanes/gitoxide/tests/MergeBaseTest.php && php -l lanes/gitoxide/examples/wordpress-merge-base.php`
  passed with no syntax errors.
- Focused merge-base test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 439 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- Lane metadata JSON:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  => `json ok`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MergeBaseFinder`, nullable commit-reader boundary, object-id validation, and
lane-local WordPress review fixtures.

## Non-Overlap

This does not repeat accepted first-vs-others graph walking, shallow missing
commit skipping, graph reuse after pairwise hydration, generation hydration,
walk-start de-duplication, stable shallow intersection skipping, timestamp and
permutation baselines, octopus ordering, transport, pack, reference,
sparse-checkout, pathspec, URL, object database, or tree-merge slices. It is
bounded to not caching incomplete stable all-head ancestor traversals across a
later shallow/promisor hydration event.
