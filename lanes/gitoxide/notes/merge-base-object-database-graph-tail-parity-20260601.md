# Gitoxide Merge-Base Object-Database Graph Tail Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T174657Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where
  `paint_down_to_common()` and `remove_redundant()` load commits through
  `Graph::get_or_insert_full_commit()`.
- Inspected `gix-revwalk/src/graph/commit.rs`, where object-backed
  `LazyCommit::to_owned()` records parents and stops after the committer
  token; it does not decode commit message or later extra-header tail bytes
  for graph walking.
- Inspected `gix-revwalk/src/graph/mod.rs`, where commit-graph lookups and
  object-backed lookups both materialize only graph-relevant commit fields for
  revision graph algorithms.

## Native PHP Delta

- `MergeBaseFinder::fromObjectDatabase()` now uses a graph-only commit parser
  for merge-base walks instead of full `Commit::parse()`.
- The graph parser validates tree, parents, author, and committer object/time
  fields, then intentionally stops after the committer header. Full commit
  parsing remains strict elsewhere.
- Added focused object-database coverage where the shared release-baseline
  commit has valid graph fields but a malformed tail without a header/message
  separator; merge-base traversal still finds the baseline.
- Extended the WordPress merge-base example with the same object-database graph
  tail scenario.

## Red-First Evidence

- After adding the focused test and before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 478 assertions, 1 failures`; the malformed tail failed with
  `Commit extra header has no value separator`.

## Verification

- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 485 assertions, 0 failures`.
- PHP lint, example smoke, JSON validation, and whitespace checks are recorded
  in the handoff response for this worker.
- Full upstream Cargo workspace runner was not executed.
- Root harness was not run; this is an isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native loose-object
storage, `ObjectDatabase`, commit signature parsing, and the existing
merge-base graph walker.

## Non-Overlap

This does not repeat accepted first-vs-others graph-walk mode, SHA-1/SHA-256
object-id validation, priority ordering, stale-queue stopping, commit-graph
generation metadata, generation bounds, missing-generation infinity,
redundant pruning, timestamp/permutation/generated baselines, octopus
ordering, shallow missing-commit handling, graph hydration reuse, non-commit
object skipping, SHA-256 object-database merge-base walking, transport, pack,
reference, config, sparse-checkout, pathspec, or tree-merge slices. It is
bounded to object-database-backed merge-base graph materialization stopping at
the committer header like Gitoxide's revision graph.
