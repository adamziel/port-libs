# Gitoxide Merge-Base Commit-Graph Generation Bound Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T062034Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `GenThenTime` orders graph-walk queue entries by commit-graph generation and
  committer time, and missing commit-graph generation is represented with
  `gix_commitgraph::GENERATION_NUMBER_INFINITY`.
- Inspected `gix-commitgraph/src/lib.rs`, where
  `GENERATION_NUMBER_MAX` is `0x3fffffff` and
  `GENERATION_NUMBER_INFINITY` is `0xffffffff`.
- Inspected `gix-commitgraph/src/file/commit.rs`, where commit-graph generation
  is read from the 30-bit generation field, so graph-backed commits cannot
  validly expose values above `GENERATION_NUMBER_MAX`.

## Native PHP Delta

- `MergeBaseFinder` now uses explicit Gitoxide generation sentinels:
  `0xffffffff` for missing/infinite generation and `0x3fffffff` as the largest
  valid provider value.
- Custom commit-graph generation providers now reject negative values and values
  above `0x3fffffff` instead of accepting arbitrary non-negative PHP integers.
- Recursive native generation fallback is capped at `0x3fffffff`, matching the
  upstream commit-graph cap.
- The WordPress merge-base fixture/example now covers a valid maximum-generation
  provider and verifies invalid provider values are rejected.

## Red-First Evidence

- After adding the focused test and before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 396 assertions, 2 failures`; the invalid generation provider
  did not throw, and the WordPress example reported
  `invalidCommitGraphGenerationRejected` as false.

## Verification

- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 407 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-merge-base.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- Metadata validation:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  => `json ok`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
commit fixtures, commit timestamp parsing, object-id validation, merge-base
graph walk, and lane-local WordPress merge-base fixture/example.

## Non-Overlap

This does not repeat accepted first-vs-others graph-walk mode, SHA-1/SHA-256
validation, priority ordering, stale-queue stopping, commit-graph generation
metadata reads, generation infinity for missing provider values, redundant-prune
generation bounds, timestamp/permutation/generated baselines, octopus ordering,
shallow missing-commit handling, graph hydration reuse, walk-start de-dup,
transport, pack, ref, config, sparse-checkout, pathspec, object database, or
tree-merge slices. It is bounded to commit-graph generation numeric bounds
inside merge-base graph walking.
