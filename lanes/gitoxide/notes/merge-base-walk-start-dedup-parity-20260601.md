# Gitoxide Merge-Base Walk-Start De-Dup Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T051422Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`. Upstream
  `remove_redundant()` temporarily marks candidate parents as `STALE` only to
  de-duplicate `walk_start`, then clears those markers before the pruning walk.
- Inspected `gix-revision/tests/fixtures/make_merge_base_repos.sh` and the
  generated `1_disjoint.baseline` shortcut rows used by
  `gix-revision/tests/revision/merge_base.rs`.

## Native PHP Delta

- `MergeBaseFinder::removeRedundantCandidates()` now clears the temporary
  stale markers on sorted walk-start commits before walking, matching
  Gitoxide's de-duplication boundary instead of carrying collection-time state
  into the actual pruning pass.
- `MergeBaseTest.php` adds focused generated-disjoint shortcut assertions:
  `first` contained in `others` returns `first` without graph reads, while
  unrelated disjoint heads still produce no merge base.
- The WordPress merge-base example now exposes the same shortcut for a plugin
  review head plus an archived unrelated review branch, and verifies the
  shortcut performs no commit reads.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 388 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 399 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 7565 assertions, 0 failures`.
- Touched example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.
- Metadata validation:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` =>
  `json ok`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
commit fixtures, object-id validation, `MergeBaseFinder` graph walk, and
WordPress merge-base fixture/example.

## Non-Overlap

This does not repeat accepted first-vs-others graph-walk mode, SHA-1/SHA-256
validation, stale-queue stopping, commit-graph generation provider behavior,
generation infinity, redundant-prune generation bounds, timestamp-skew
baselines, permutation baselines, three-head generated baselines, octopus
ordering, shallow missing-commit handling, graph hydration reuse, transport,
pack, ref, config, sparse-checkout, pathspec, object database, or tree-merge
slices. It is bounded to the upstream `remove_redundant()` walk-start stale-bit
clearing boundary and generated `1_disjoint.baseline` shortcut rows.
