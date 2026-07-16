# Tree Merge Conflicting-Rename Resolve-Tree Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260531T204639Z`

Base accepted HEAD: `4cd5c83f2f1b57c5b3e318d737d8c94ee34892b6`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Fixture cases: `conflicting-rename` and `conflicting-rename-2`
- Upstream resolve-tree files inspected from the generated archive:
  - `conflicting-rename/.git/resolve-A-B-with-ours.tree`
  - `conflicting-rename/.git/resolve-B-A-with-ours.tree`
  - `conflicting-rename-2/.git/resolve-A-B-with-ours.tree`
  - `conflicting-rename-2/.git/resolve-B-A-with-ours.tree`

## Behavior

Resolving a directory rename/rename tree conflict with `ours` must not simply
copy the chosen directory tree. Gitoxide resolves the tree location to the
chosen side, then still applies clean content changes from the opposite side
inside that directory. For the targeted fixtures, `a-renamed` or
`a/sub-renamed` keeps the chosen path but its changed `x.f`/`y.f` content
becomes `1..6`, matching the upstream resolve-tree object.

`TreeMergeResult::resolveTreeConflicts()` now detects tree-shaped
`rename-rename` and `nested-directory-rename` conflicts and recursively merges
the conflicting subtrees before installing the selected path. Remaining nested
conflicts, if any, are rebased under the selected directory path.

## Verification

- Red-first focused run with only the new assertions:
  `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed with
  `1 test files, 549 assertions, 2 failures`; both failures kept `1..5`
  instead of upstream `1..6`.
- `php -l lanes/gitoxide/src/TreeMergeResult.php`
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
- `php -l lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed with
  `1 test files, 559 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php` exited `0`
  and reported `directoryRenameConflictResolution.oursResolvedClean=true`,
  `routeIncludesOtherSidePermissionCallback=true`, and `indexStagesAfterResolution=0`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `39 test files, 5433 assertions, 0 failures`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native tree/object store,
recursive tree merge, conflict metadata, and existing WordPress recursive merge
example.

## Non-Overlap

This does not repeat accepted conflicting-rename merge-index fixture parity,
renamed-symlink resolve-tree parity, rename-add-symlink resolve-tree parity,
merge-base graph walking, transport, protocol, object database, reference, or
pathspec slices. The new mapped behavior is specifically resolve-tree content
application for directory rename/rename conflicts.
