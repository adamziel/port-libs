# Gitoxide Merge-Base Shallow Missing-Commit Graph-Walk Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T003607Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `paint_down_to_common()` and `remove_redundant()` use
  `Graph::get_or_insert_full_commit()` and do not run the flag update callback
  when a commit cannot be found.
- Inspected `gix-revwalk/src/graph/mod.rs`, where missing commits return
  `Ok(None)` and traversal skips them, matching shallow repository behavior.

## Native PHP Delta

- `MergeBaseFinder` commit readers may now return `null` for absent commits.
- The pairwise and first-vs-others graph walk skips missing start, other, and
  parent commits instead of treating them as corrupt commit-reader output.
- Redundant-base pruning skips absent candidate parents, so shallow missing
  ancestors do not abort pruning.
- Recursive generation fallback ignores missing parents while still validating
  object-id formats for parent links that are present in parsed commits.
- `examples/wordpress-merge-base.php` now models a shallow archived review
  branch whose parent object is absent while the active plugin/theme reviews
  still find the release baseline.

## Red-First Evidence

- After adding the focused missing-commit test and before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 357 assertions, 1 failures` with
  `Commit reader must return PortLibs\Gitoxide\Commit`.

## Verification

- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 368 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
commit fixtures, commit timestamp parsing, object-id validation, and merge-base
graph walk implementation; missing shallow commits are represented by a
`null` reader result.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-256 graph
walking, stale-queue stopping after a discovered base, commit-graph generation
metadata, redundant-prune generation bounds, generated timestamp/permutation
baselines, octopus ordering, transport, pack, reference, sparse-checkout,
pathspec, or tree-merge slices. It is bounded to Gitoxide's shallow missing
commit skip behavior during merge-base graph walking.
