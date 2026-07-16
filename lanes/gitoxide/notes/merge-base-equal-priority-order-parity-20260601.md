# Gitoxide Merge-Base Equal-Priority Order Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T163130Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where the walk uses a
  `GenThenTime` key containing only generation and commit time, and
  `remove_redundant()` preserves candidate discovery order after stale/redundant
  filtering.
- Inspected `gix-revwalk/src/queue.rs`, where the priority queue orders by the
  supplied key, not by object id.
- Inspected `gix-revision/tests/revision/merge_base.rs` and
  `gix-revision/tests/fixtures/make_merge_base_repos.sh`, where generated
  merge-base baselines are compared to `git merge-base --all`.

## Native PHP Delta

- Removed the object-id tie-breaker from `MergeBaseFinder` graph-walk queue
  priorities. Equal generation/time commits now keep insertion/discovery order.
- Changed final merge-base candidate ordering to preserve original candidate
  discovery order when generation and commit time are equal.
- Added an equal-time criss-cross graph from a local Git oracle where
  `git merge-base --all` returns `c0b4...` before `82be...`, even though
  `82be...` is lexicographically smaller.
- Extended the WordPress merge-base fixture/example with the same graph and
  assertions for both direct `mergeBases()` and graph-walk
  `mergeBasesAgainst()` output.

## Red-First Evidence

- Focused baseline before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 467 assertions, 0 failures`.
- Adding the equal-priority assertion before removing the queue object-id
  tie-break exposed reversed candidate order for existing criss-cross coverage.

## Verification

- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-merge-base.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.
- Focused test:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 477 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.
- JSON status decode:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  => `json ok`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP commit
fixtures, `MergeBaseFinder`, commit-graph generation callbacks, and the
lane-local WordPress merge-base example. No Git/Rust subprocess is required for
runtime behavior.

## Non-Overlap

This does not repeat accepted stale-queue graph walking, commit-graph cache
lookup, generation bounds, generation hydration, redundant pruning, command
output formatting, octopus ordering, SHA-1/SHA-256 object database behavior,
object-database non-commit handling, shallow missing parents, transport, pack,
reference, sparse-checkout, pathspec, URL/refspec, partial-clone, or tree-merge
slices. It is bounded to equal-priority merge-base graph-walk queue and output
ordering.
