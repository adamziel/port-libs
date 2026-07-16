# Tree Merge Conflicting-Rename-Complex Resolve-Tree Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260531T215025Z`

Base accepted HEAD: `9ef60eb910c3006c081a236c1ec05f4d0e7024c4`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Fixture case: `conflicting-rename-complex`
- Resolve-tree files inspected:
  - `conflicting-rename-complex/.git/resolve-A-B-with-ancestor.tree`
  - `conflicting-rename-complex/.git/resolve-A-B-with-ours.tree`
  - `conflicting-rename-complex/.git/resolve-B-A-with-ancestor.tree`
  - `conflicting-rename-complex/.git/resolve-B-A-with-ours.tree`

## Behavior

The accepted merge-result fixture already represented the unforced
`conflicting-rename-complex` merge shape. This slice adds the upstream
resolve-tree behavior for the same fixture:

- Ancestor resolution keeps the original `a` directory while preserving the
  one clean non-conflicting replacement path under `a-renamed`.
- Choosing the renamed side keeps the renamed directory, its merged root copy,
  and clean replacement paths, while dropping the forced replacement conflict.
- Choosing the replacement side keeps only the hoisted replacement leaves under
  the reconciled `a-renamed` location.

`TreeMergeConflict` now carries optional lane-local context for the synthetic
`directory-rename-subtree-replacement` conflict. `TreeMergeResult` uses that
context to resolve the subtree-replacement conflict and its related old-path
`rename-delete` / `directory-rename-suggested` conflicts as one unit.

## Verification

- Red-first focused probe:
  `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed with
  `1 test files, 559 assertions, 1 failures` before the source change.
- `php -l lanes/gitoxide/src/TreeMergeConflict.php`
- `php -l lanes/gitoxide/src/TreeMerge.php`
- `php -l lanes/gitoxide/src/TreeMergeResult.php`
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
- `php -l lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed with
  `1 test files, 590 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php` exited `0`
  and reported clean ancestor/ours/theirs subtree-replacement resolutions with
  zero index stages after ours resolution.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `39 test files, 5867 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native tree/blob object
store, recursive tree merge, merge-index expansion, and existing WordPress
recursive tree-merge example.

## Non-Overlap

This does not repeat the accepted merge-result parity for
`conflicting-rename-complex`, the earlier resolve-tree parity for
`conflicting-rename` / `conflicting-rename-2`, renamed-symlink resolve-tree
behavior, transport/protocol work, reference transactions, object database,
pathspec, or merge-base graph-walk slices. It targets the remaining
resolve-tree outputs for the complex subtree-replacement fixture.
