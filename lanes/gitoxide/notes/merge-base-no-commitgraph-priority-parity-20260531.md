# Gitoxide Merge-Base No-Commitgraph Priority Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T102846Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where `GenThenTime`
  uses a commit-graph generation when present and
  `GENERATION_NUMBER_INFINITY` when no commit graph is available.
- Inspected `gix/src/repository/revision.rs`, where repository merge-base
  helpers pass `repo.commit_graph_if_enabled()` into the revision graph.

## Native PHP Delta

- `MergeBaseFinder` now accepts a `useCommitGraphGenerations` option.
- The existing default preserves accepted generation-plus-committer-time
  ordering for commit-graph-backed graph walks.
- Passing `useCommitGraphGenerations: false` maps the no-commitgraph upstream
  path where all generations compare equally and independent merge-base
  ordering falls back to committer time.
- `MergeBaseFinder::fromObjectDatabase()` forwards the same option.
- `examples/wordpress-merge-base.php` now models a WordPress compatibility
  deployment graph where commit-graph ordering selects a deeper legacy base,
  while no-commitgraph ordering selects the newer security baseline.

## Verification

- Before focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 56 assertions, 0 failures`.
- After focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 68 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `38 test files, 4195 assertions, 0 failures`.
- Changed example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- PHP lint:
  `php -l` passed for `src/MergeBaseFinder.php`,
  `tests/MergeBaseTest.php`, `fixtures/wordpress-merge-base.php`, and
  `examples/wordpress-merge-base.php`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP commit
fixtures, commit parsing, and object-database merge-base readers.

## Non-Overlap

This does not repeat accepted first/others graph-walk, SHA-256 object-format
validation, priority ordering when commit-graph generations are available,
octopus helpers, tree/pathspec, protocol, pack, config, ref, or transport
clusters. It is bounded to Gitoxide's no-commitgraph generation fallback for
merge-base graph walks.
