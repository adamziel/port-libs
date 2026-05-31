# Tree Merge Type-Change-And-Renamed Resolve-Tree Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260531T225125Z`

Base accepted HEAD: `292ada6b86cc431f7b1537075eacedfb4e905cf4`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Fixture case: `type-change-and-renamed`
- Resolve-tree files inspected:
  - `type-change-and-renamed/.git/resolve-A-B-with-ancestor.tree`
  - `type-change-and-renamed/.git/resolve-A-B-with-ours.tree`
  - `type-change-and-renamed/.git/resolve-B-A-with-ancestor.tree`
  - `type-change-and-renamed/.git/resolve-B-A-with-ours.tree`

## Behavior

The fixture has side A change `link` from a symlink into a regular file while
side B renames the original symlink to `link-renamed`. Upstream resolve-tree
behavior keeps only one side's path:

- Ancestor resolution keeps `link` as the original symlink.
- A-side resolution keeps `link` as the regular file.
- B-side resolution keeps only `link-renamed` as the symlink.

The native resolver now carries the original source path for rename/type-change
conflicts and removes that path when resolving to the renamed entry. This avoids
leaving both the type-changed source path and renamed symlink in the resolved
tree.

## Verification

- Red-first focused run before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed with
  `1 test files, 602 assertions, 1 failures`; resolving to the renamed side
  left both `link` and `link-renamed`.
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed with
  `1 test files, 610 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `39 test files, 6096 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php` exits `0`
  and reports `typeChangeRenamedSymlinkResolution.theirsResolvedClean=true`
  with only `renamed-loader.php` in `mu-plugins`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native tree/blob object storage,
recursive tree merge conflict metadata, and existing resolve-tree application.

## Non-Overlap

This does not repeat accepted conflicting-rename, conflicting-rename-complex,
renamed-symlink, rename-add-symlink, rename-within-rename, transport, protocol,
object database, reference, pathspec, or merge-base slices. The new mapped
behavior is specifically the `type-change-and-renamed` resolve-tree output where
a renamed symlink is selected over a type-changed original path.
