# Gitoxide Merge-Base Priority Graph-Walk Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T095602Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`,
  `gix-revision/src/merge_base/mod.rs`, and
  `gix-revision/tests/revision/merge_base.rs`.
- Inspected `gix-revision/tests/fixtures/make_merge_base_repos.sh`, whose
  generated baselines include timestamp-skewed merge-base graphs.

## Native PHP Delta

- `MergeBaseFinder::mergeBases()` and `mergeBasesAgainst()` now order
  independent merge-base candidates by generation and committer timestamp
  before existing deterministic tie-breakers.
- The behavior maps Gitoxide's `PriorityQueue<GenThenTime, ObjectId>` boundary
  where graph traversal prefers newer generation/time commits and then removes
  redundant ancestors.
- The WordPress merge-base fixture/example now includes a hotfix review graph
  with two independent common baselines and confirms the newer security
  baseline is selected first.

## Red-First Evidence

- Before source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 50 assertions, 1 failures` on lexicographic independent-base
  ordering.

## Verification

- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 56 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `38 test files, 4005 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
commit parsing, `CommitSignature` timestamp access, and lane-local commit graph
fixtures.

## Non-Overlap

This does not repeat the accepted merge-base first/others graph-walk mode,
SHA-256 object-format validation, multi-head octopus helper, tree multiple-base
fixture, or protocol/config/pack/ref slices. It is bounded to candidate
priority ordering for graph-walk merge bases.
