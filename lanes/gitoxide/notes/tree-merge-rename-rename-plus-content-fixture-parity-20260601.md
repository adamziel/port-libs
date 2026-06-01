# Tree Merge Rename/Rename Plus Content Fixture Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T043922Z`

Base accepted HEAD: `a9f4989344098e67e1082ce806a8270acd26ace6`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Fixture case: `rename-rename-plus-content`
- Inspected generated records:
  - `baseline.cases`
  - `rename-rename-plus-content/A-B.merge-info`
  - `rename-rename-plus-content/B-A.merge-info`
  - `rename-rename-plus-content/.git/resolve-A-B-with-ancestor.tree`
  - `rename-rename-plus-content/.git/resolve-A-B-with-ours.tree`
  - `rename-rename-plus-content/.git/resolve-B-A-with-ancestor.tree`
  - `rename-rename-plus-content/.git/resolve-B-A-with-ours.tree`

## Behavior

When both sides rename `foo` to different blob paths and also modify content,
the unresolved merge tree keeps the ancestor content at `foo`, but the merge
index records the side stages at their renamed paths: ancestor `foo`, ours
`bar`, and theirs `baz`. Resolving the tree conflict with `ancestor` restores
`foo`; resolving with `ours` chooses `bar`; resolving the reversed B/A merge
with `ours` chooses `baz`, matching the upstream resolve-tree fixture shape.

`MergeIndexFile::entriesForResult()` now applies the rename/rename stage-path
mapping to non-tree entries too. Tree-shaped rename conflicts already used the
side entry filename; blob and symlink entries now follow the same upstream
merge-index path rule.

## Verification

- Red-first focused run with only the new assertions:
  `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed with
  `1 test files, 696 assertions, 1 failures`; stages 2 and 3 were emitted at
  `foo` instead of upstream paths `bar` and `baz`.
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed with
  `1 test files, 717 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `40 test files, 7392 assertions, 0 failures`.
- `php -l lanes/gitoxide/src/MergeIndexFile.php` passed.
- `php -l lanes/gitoxide/tests/TreeMergeTest.php` passed.
- `git diff --check -- lanes/gitoxide` passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native recursive tree merge,
merge-index writing, tree conflict resolution, and the existing in-memory
Git object store used by the tree-merge tests.

## Non-Overlap

This does not repeat accepted directory/file, renamed-symlink,
type-change-and-renamed, conflicting-rename, conflicting-rename-complex,
rename-within-rename, super-2, or rename-rename-delete-delete resolve-tree
slices. The new mapped behavior is specifically upstream
`rename-rename-plus-content` merge-index stage-path and resolve-tree fixture
parity.
