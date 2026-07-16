# Tree Merge Change/Delete Resolve-Tree Parity

Micro-slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T080254Z`

## Upstream Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Source file: `gix-merge/tests/fixtures/tree-baseline.sh`
- Fixture case: `change-and-delete`
- Resolve-tree expectations inspected:
  - `make_resolve_tree ancestor A B`
  - `make_resolve_tree ancestor B A`
  - `make_resolve_tree ours A B`
  - `make_resolve_tree ours B A`

## Behavior

The native PHP tree merge already represented the unresolved `change-and-delete`
shape. This slice adds upstream-backed resolve-tree fixture parity: resolving
with `ancestor` restores the original tree and symlink, resolving with `ours`
keeps the changed file and type-changed link, resolving with `theirs` removes
the deleted side, and the reversed `ours` case also resolves to an empty tree.

The WordPress recursive tree-merge example now includes the same class of
deployment conflict for a plugin bootstrap tree plus a mu-plugin loader, showing
ancestor/ours/theirs resolution paths without shelling out to Git.

## Verification

- Before this slice: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  passed `1 test files, 746 assertions, 0 failures`.
- After adding the fixture: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  passed `1 test files, 772 assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
- `php -l lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`
- `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`
- `git diff --check -- lanes/gitoxide`

## Dependency Closure

No new support component is needed. This reuses the native `TreeMerge`,
`TreeMergeResult`, in-memory Git object store helpers, and recursive WordPress
tree-merge example path.

## Non-Overlap

This does not repeat accepted conflicting-rename, conflicting-rename-2,
conflicting-rename-complex, renamed-symlink, type-change-and-renamed,
directory-file, super-1, super-2, rename-within-rename, submodule, or
rename-rename-delete-delete resolve-tree slices. The mapped behavior is
specifically upstream `change-and-delete` resolve-tree parity.
