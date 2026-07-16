# Gitoxide Merge-Base Object Database Non-Commit Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T135506Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revwalk/src/graph/mod.rs`, where `try_lookup()` returns
  `None` when an object exists but is not a commit.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `paint_down_to_common()` and `remove_redundant()` skip `None` graph lookup
  results while walking starts and parents.

## Native PHP Delta

- `MergeBaseFinder::fromObjectDatabase()` now treats non-commit objects as
  absent graph nodes instead of throwing at the object-database reader
  boundary.
- Missing objects, promisor misses, and non-commit loose objects now share the
  same upstream-shaped graph skip path; malformed commit objects and decode
  errors are still surfaced.
- `examples/wordpress-merge-base.php` now covers review commits whose parent
  oid names a cached asset/blob object and verifies no merge base is reported
  from that non-commit ancestor.

## Red-First Evidence

- After adding the focused test and before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 445 assertions, 1 failures`; failure was
  `Expected a commit object for ... got blob`.

## Verification

- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php && php -l lanes/gitoxide/tests/MergeBaseTest.php && php -l lanes/gitoxide/examples/wordpress-merge-base.php`
  passed.
- Focused merge-base test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 453 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 9652 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- Lane metadata JSON:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  => `json ok`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses the native loose-object
store, object database, commit parser, and existing merge-base graph walker.

## Non-Overlap

This does not repeat accepted first-vs-others graph walking, stale-queue
stopping, missing shallow commit skips, graph/generation hydration reuse,
object-database missing-parent hydration, SHA-256 object-database merge-base
walking, command-output formatting, octopus ordering, transport, pack,
reference, sparse-checkout, pathspec, URL/refspec, partial-clone, or
tree-merge slices. It is bounded to upstream graph lookup behavior for
non-commit loose objects during merge-base object-database walks.
