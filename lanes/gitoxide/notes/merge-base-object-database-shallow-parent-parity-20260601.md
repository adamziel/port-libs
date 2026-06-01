# Gitoxide Merge-Base Object Database Shallow Parent Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T073336Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `paint_down_to_common()` and `remove_redundant()` call
  `Graph::get_or_insert_full_commit()` for starts and parents.
- Inspected `gix-revwalk/src/graph/mod.rs`, where
  `get_or_insert_full_commit()` returns `Ok(None)` for missing commits and does
  not run the traversal update callback.

## Native PHP Delta

- `MergeBaseFinder::fromObjectDatabase()` now maps true object-database misses
  to `null` reader results, matching the existing custom-reader shallow
  graph-walk behavior.
- Non-commit objects still fail as before, and object corruption or malformed
  data exceptions are not swallowed.
- Added an object-database-backed test that starts with two loose review
  commits referencing a missing shallow release parent, then writes that parent
  into the same loose object store and verifies the reused finder discovers it.
- Extended the WordPress merge-base example with the same loose-object database
  deployment-review scenario.

## Red-First Evidence

- After adding the focused object-database test and before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 407 assertions, 1 failures` with
  `Object not found in database: 46cb15b1e7e851fd4c3576e75823e6e573811e50`.

## Verification

- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 415 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 8034 assertions, 0 failures`.
- Touched example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
loose object store, object database, commit parser, and merge-base graph walker.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-1/SHA-256
validation, priority ordering, stale-queue stopping, custom-reader shallow
missing-commit handling, commit-graph provider metadata, generation infinity,
redundant pruning, timestamp/permutation/generated baselines, octopus ordering,
graph hydration reuse, walk-start de-dup, transport, pack, ref, config,
sparse-checkout, pathspec, or tree-merge slices. It is bounded to
`fromObjectDatabase()` parity for missing shallow/promisor commit objects during
merge-base graph walking.
