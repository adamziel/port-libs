# Gitoxide Merge-Base Graph-Walk Parity - 2026-05-31

Micro-slice: `gitoxide-merge-base-graph-walk-parity-20260531T085026Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-revision/src/merge_base/function.rs`.
- Inspected `git show HEAD:gix-revision/tests/revision/merge_base.rs`.

## Native PHP Delta

- `MergeBaseFinder::mergeBasesAgainst()` and `mergeBaseAgainst()` now map `gix_revision::merge_base(first, others)`.
- The new graph-walk mode treats all `others` heads as the other side of a hypothetical merge, so unrelated other heads do not block a base shared by `first` and a related other head.
- Empty `others` and `first` appearing in `others` return `first` without reading commit objects, matching the upstream shortcut.
- Existing `mergeBasesMany()` remains the stable octopus/all-head intersection helper.
- `examples/wordpress-merge-base.php` now shows the WordPress release-baseline contrast between graph-walk mode and octopus mode when an archived unrelated branch is included.

## Verification

- Before focused test: `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` => `1 test files, 20 assertions, 0 failures`.
- After focused test: `php tools/run-tests.php lanes/gitoxide/tests/MergeBaseTest.php` => `1 test files, 32 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests` => `34 test files, 3136 assertions, 0 failures`.
- Changed example smoke: `php lanes/gitoxide/examples/wordpress-merge-base.php` => exit `0`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP commit parser/object database abstractions and lane-local fixture commits.

## Non-Overlap

This slice does not repeat the accepted multi-head octopus merge-base, recursive tree merge multiple-base fixture, pack/MIDX validation, protocol v2, config/pathspec, or transport clusters. It is bounded to `gix_revision::merge_base(first, others)` graph-walk semantics.
