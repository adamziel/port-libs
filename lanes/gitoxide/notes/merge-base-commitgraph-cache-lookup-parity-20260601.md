# Gitoxide Merge-Base Commit-Graph Cache Lookup Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T151433Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revwalk/src/graph/mod.rs`, where `try_lookup()` checks the
  commit-graph cache before the object database and returns commit metadata
  without object lookup when the cache contains the id.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `paint_down_to_common()` and `remove_redundant()` use
  `Graph::get_or_insert_full_commit()` for starts and parents.

## Native PHP Delta

- `MergeBaseFinder` now accepts an optional `commitGraphCommit` provider.
- Commit lookup consults that provider before the object-reader callback,
  matching upstream commit-graph cache precedence over object database lookup.
- The existing `commitGraphGeneration` provider still supplies generation
  numbers, and the new provider only supplies commit parents/timestamps. A
  `null` provider result falls back to the object reader so shallow/object
  database miss behavior is preserved.
- The WordPress merge-base fixture/example now covers plugin/theme review
  commits supplied only by commit-graph metadata while an unrelated archived
  branch still falls back to object lookup.

## Red-First Evidence

- After adding the focused test and before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 453 assertions, 1 failures`; failure was
  `Unknown named parameter $commitGraphCommit`.

## Verification

- Focused merge-base test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 467 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 9927 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php && php -l lanes/gitoxide/tests/MergeBaseTest.php && php -l lanes/gitoxide/fixtures/wordpress-merge-base.php && php -l lanes/gitoxide/examples/wordpress-merge-base.php`
  passed.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP commit
fixtures, the existing merge-base graph walker, and a lane-local optional
provider for commit-graph metadata. No external Git/Rust process is required.

## Non-Overlap

This does not repeat accepted first-vs-others graph walking, shallow missing
commit skipping, stale-queue stopping, commit-graph generation-only metadata,
generation bounds, generation hydration, missing-generation infinity,
redundant pruning, graph reuse hydration, stable ancestor cache hydration,
object-database shallow parent handling, non-commit parent skipping, SHA-256
graph walking, octopus ordering, transport, pack, reference, sparse-checkout,
pathspec, URL/refspec, partial-clone, or tree-merge slices. It is bounded to
upstream commit-graph cache lookup precedence before object database reads.
