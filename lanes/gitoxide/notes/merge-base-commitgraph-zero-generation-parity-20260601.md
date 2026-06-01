# Gitoxide Merge-Base Commit-Graph Zero Generation Parity - 2026-06-01

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260601T202252Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`, where graph-walk
  priority uses `GenThenTime` from `gix_revwalk::graph::Commit`.
- Inspected `gix-commitgraph/src/lib.rs`, where `GENERATION_NUMBER_MAX` is
  `0x3fffffff` and infinity is reserved as `0xffffffff`.
- Inspected `gix-commitgraph/src/verify.rs` and
  `gix-commitgraph/tests/access/mod.rs`, where valid root commit generations
  are computed and asserted as `1`; generation `0` is not a valid real
  commit-graph generation.

## Native PHP Delta

- `MergeBaseFinder` now rejects provided commit-graph generations below `1`
  while still accepting `null` as missing commit-graph metadata and preserving
  the existing `0x3fffffff` upper bound.
- `MergeBaseTest.php` adds direct graph-walk rejection coverage for generation
  `0` next to the existing over-maximum generation rejection.
- `fixtures/wordpress-merge-base.php` and `examples/wordpress-merge-base.php`
  now expose the same lower-bound rejection for deployment-review merge-base
  discovery.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 485 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` =>
  `1 test files, 488 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` =>
  `40 test files, 10761 assertions, 0 failures`.
- PHP lint:
  `php -l lanes/gitoxide/src/MergeBaseFinder.php`,
  `php -l lanes/gitoxide/tests/MergeBaseTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-merge-base.php`, and
  `php -l lanes/gitoxide/examples/wordpress-merge-base.php` passed.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-merge-base.php >/tmp/gitoxide-wordpress-merge-base.out && wc -c /tmp/gitoxide-wordpress-merge-base.out`
  => `0 /tmp/gitoxide-wordpress-merge-base.out`.
- Metadata/whitespace:
  `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane status json ok\n";'`
  => `lane status json ok`.
  `git diff --check -- lanes/gitoxide` => exit `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
commit fixtures, commit-graph generation provider hook, and WordPress
merge-base example. It does not invoke Git, Cargo, network, or live provider
services.

## Non-Overlap

This deepens the already represented merge-base commit-graph generation-bound
cluster without increasing mapped coverage. It does not repeat accepted
first-vs-others graph walking, stale-queue stopping, commit-graph commit
lookup, missing-generation infinity, redundant-prune generation bounds,
timestamp/permutation/generated baselines, octopus ordering, hydration reuse,
object-database graph walking, credential, transport, pack/MIDX, reference,
config, sparse-checkout, pathspec, URL/refspec, partial-clone, or tree-merge
slices.
