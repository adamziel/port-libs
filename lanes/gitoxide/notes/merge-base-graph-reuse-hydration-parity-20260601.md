# Gitoxide Merge-Base Graph Reuse Hydration Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T015217Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revwalk/src/graph/mod.rs`. `Graph::get_or_insert_full_commit()`
  returns `Ok(None)` when an object lookup misses and does not insert that id
  into the graph.
- Inspected `gix-revision/src/merge_base/function.rs`.
  `paint_down_to_common()` skips missing start/parent commits through
  `get_or_insert_full_commit()`, while repeated calls clear commit flags but
  reuse graph commit data. A later object lookup can therefore observe a
  shallow/promisor commit that became available after an earlier miss.

## Native PHP Delta

- `MergeBaseFinder` no longer permanently caches missing commit ids. Successful
  `Commit` objects remain cached, but a `null` lookup is only a miss for that
  lookup, matching the upstream graph's non-insertion behavior.
- Added a focused graph-reuse test that first misses a shared release ancestor,
  then hydrates it into the same reader and verifies the reused finder returns
  it as the merge base.
- Extended the WordPress merge-base fixture/example with the same hydrated
  promisor release-baseline path.

## Verification

- Red-first focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  failed `maps upstream graph reuse by not pinning missing commits after
  hydration`; expected the hydrated release ancestor, actual result was empty.
- Focused test after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 378 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 6770 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-merge-base.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` =>
  no syntax errors.
- Lane metadata JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` =>
  `json ok`.
- Touched example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP commit
fixtures, the existing `MergeBaseFinder` graph walk, and the existing WordPress
merge-base example; no external Git/Rust process is required.

## Non-Overlap

This does not repeat accepted stale-queue stopping, commit-graph generation
metadata, missing generation infinity, redundant pruning, timestamp-skew,
permutation archive, SHA-256, octopus ordering, partial-clone object database
hydration refresh, tree-merge, protocol, transport, ref, or config slices. It
is bounded to the upstream graph miss semantics for reused merge-base walks
after a missing shallow/promisor ancestor is hydrated.
