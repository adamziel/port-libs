# Tree Merge File/Tree Replacement Resolve-Tree Parity

Micro-slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T130911Z`

## Upstream Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Source file: `gix-merge/tests/fixtures/tree-baseline.sh`
- Fixture cases:
  - `non-tree-to-tree`
  - `tree-to-non-tree`
  - `tree-to-non-tree-with-rename`
- Resolve-tree expectations inspected:
  - `make_resolve_tree ancestor A B`
  - `make_resolve_tree ancestor B A`
  - `make_resolve_tree ours A B`
  - `make_resolve_tree ours B A`

## Behavior

This slice adds the missing upstream-backed resolve-tree assertions for file
and directory replacements at the same path. The native PHP merge already
materialized the unresolved replacement conflicts; the new parity test verifies
that conflict resolution writes the selected side back to the original path
rather than leaving relocated `~A` / `~B` entries.

The covered upstream shapes are:

- file replaced by a directory: `ancestor` restores the base blob, `ours` keeps
  the changed blob, and reversed `ours` keeps the directory tree.
- directory replaced by a file: `ancestor` restores the base subtree, `ours`
  keeps the changed subtree, and reversed `ours` keeps the replacement file.
- directory replaced by an empty renamed file: same as above, with the
  replacement blob body preserved as empty.

The WordPress recursive tree-merge example now reports ancestor and reversed
ours resolution for the `wp-content/cache` file/directory deployment conflict,
so the user-visible smoke covers both original-path blob restoration and
directory preservation after side reversal.

## Verification

- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
- `php -l lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  - `1 test files, 878 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`
  - passed; output includes `directoryFileResolution.ancestorResolvedClean`,
    `directoryFileResolution.reverseOursResolvedClean`, and preserved
    `reverseOursCacheEntries`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 9477 assertions, 0 failures`
- `git diff --check -- lanes/gitoxide`

## Dependency Closure

No new support component is needed. This reuses the native `TreeMerge`,
`TreeMergeResult`, recursive tree object helpers, and the existing WordPress
recursive tree-merge smoke path.

## Non-Overlap

No Gitoxide rework note existed for this lane before editing. This does not
repeat accepted directory-file, change-delete, submodule, super-1, super-2,
rename-within-rename, conflicting-rename, symlink, or rename/delete
resolve-tree slices. It specifically closes resolve-tree parity for the
upstream file/tree replacement cases that already had unresolved fixture
coverage.
