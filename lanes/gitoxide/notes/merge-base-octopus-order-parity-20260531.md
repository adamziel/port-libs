# Gitoxide Merge-Base Octopus Order Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T121121Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/mod.rs`, where
  `merge_base::octopus()` walks the ordered head list by repeatedly calling
  `merge_base(first, [next])` and keeping only the first pairwise base.
- Inspected `gix/src/repository/revision.rs`, where
  `Repository::merge_base_octopus_with_graph()` forwards that ordered
  behavior and treats an empty commit list as a missing-commit error.
- Reused the generated upstream merge-base fixture archive baseline from
  `gix-revision/tests/fixtures/generated-archives/make_merge_base_repos.tar`
  to confirm the existing `mergeBasesAgainst()` graph walk still matches all
  106 generated baseline rows.

## Native PHP Delta

- `MergeBaseFinder::mergeBaseOctopus()` now maps the upstream sequential
  octopus helper without changing the existing stable `mergeBasesMany()`
  intersection helper.
- The new helper validates homogeneous SHA-1/SHA-256 object formats, returns a
  single head unchanged, returns `null` when a sequential pair has no base, and
  preserves Gitoxide's order-sensitive first-base behavior for criss-cross
  histories.
- The WordPress merge-base fixture/example now contrasts a stable all-head
  intersection with upstream octopus ordering for hotfix branches that share
  both a legacy and a security baseline plus a legacy-only review branch.

## Verification

- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php &&
  php -l lanes/gitoxide/tests/MergeBaseTest.php &&
  php -l lanes/gitoxide/fixtures/wordpress-merge-base.php &&
  php -l lanes/gitoxide/examples/wordpress-merge-base.php` =>
  all changed PHP files reported no syntax errors.
- Lane metadata JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` =>
  `json ok`.
- Focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 83 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `39 test files, 4499 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses existing native PHP
commit fixtures, `Commit` timestamp parsing, object-id validation, and
merge-base graph-walk helpers.

## Non-Overlap

This does not repeat accepted first/others graph-walk mode, SHA-256 graph
walking, no-commitgraph priority ordering, independent-base priority ordering,
stable all-head intersection behavior, tree/pathspec, protocol, pack, config,
ref, reflog, or transport clusters. It is bounded to the upstream
`merge_base::octopus()` ordered sequential helper.
