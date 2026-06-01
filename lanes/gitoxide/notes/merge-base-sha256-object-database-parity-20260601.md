# Gitoxide Merge-Base SHA-256 Object Database Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T101144Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `paint_down_to_common()` inserts full commits through
  `Graph::get_or_insert_full_commit()`.
- Inspected `gix-revwalk/src/graph/mod.rs` and `graph/commit.rs`, where
  `try_lookup()` preserves the object hash kind and `LazyCommit::to_owned()`
  parses parents/tree ids using the object hash backing the commit.

## Native PHP Delta

- `MergeBaseFinder::fromObjectDatabase()` now parses commit bodies with the
  object-id hash kind being walked: 40-byte ids use SHA-1 and 64-byte ids use
  SHA-256.
- Object-database-backed merge-base walks can now traverse SHA-256 loose
  commit objects without rejecting their 64-byte tree and parent headers.
- The WordPress merge-base example now includes a SHA-256 object-database
  plugin/theme review graph and verifies the release baseline through pairwise
  and first-vs-others graph walking.

## Red-First Evidence

- With the focused SHA-256 object-database test added before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 422 assertions, 1 failures` with
  `Commit tree must be a 40-character sha1 hex object id`.

## Verification

- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 432 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 8704 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses the native
`ObjectDatabase`, `LooseObjectStore`, `GitObject`, `Commit` parser, and
`MergeBaseFinder` graph walk.

## Non-Overlap

This does not repeat accepted in-memory SHA-256 merge-base graph walking,
object-database shallow parent hydration, generation hydration, commit-graph
metadata, redundant pruning, timestamp/permutation/generated baselines,
octopus ordering, transport, pack-index/MIDX, reference, sparse-checkout,
pathspec, URL/refspec, or tree-merge slices. It is bounded to SHA-256 commit
body parsing at the object-database-backed merge-base reader boundary.
