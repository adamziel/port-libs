# Tree Merge Directory/File Resolve-Tree Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T000019Z`

Base accepted HEAD: `0e78c232d5f671d5140ddac2287b4ff3c85d5779`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Fixture case: `simple` / `side-1-2-various-conflicts`
- Resolve-tree files inspected:
  - `simple/.git/resolve-side1-side2-with-ancestor.tree`
  - `simple/.git/resolve-side1-side2-with-ours.tree`
  - `simple/.git/resolve-side2-side1-with-ancestor.tree`
  - `simple/.git/resolve-side2-side1-with-ours.tree`

## Behavior

The unforced merge relocates the file side of a directory/file conflict to an
internal `~A` or `~B` path so merge-index stages can represent both sides. The
upstream resolve-tree output does not keep that relocation name once the tree
conflict is resolved. Choosing the ancestor or file side replaces the directory
at the original path with the chosen file, while unrelated content conflicts
remain unresolved unless a content resolution is explicitly requested.

`TreeMergeConflict` context now records the original resolved path for relocated
directory/file conflicts. `TreeMergeResult::resolveTreeConflicts()` uses that
path when installing a chosen relocated entry, removing the unresolved directory
or stale relocation and writing the selected file at the original path.

## Verification

- Red-first focused run before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed with
  `1 test files, 611 assertions, 1 failures`; the resolved tree still contained
  `whatever~A`.
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed with
  `1 test files, 628 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php` exited `0`
  and reported `directoryFileResolution.oursResolvedClean=true` with the
  resolved cache entry as a blob at `wp-content/cache`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `40 test files, 6413 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native recursive tree merge,
tree/blob object storage, merge-index relocation stages, and existing
resolve-tree conflict application.

## Non-Overlap

This does not repeat accepted conflicting-rename, conflicting-rename-complex,
rename-within-rename, renamed-symlink, type-change-and-renamed,
transport/protocol, object database, reference, pathspec, or merge-base slices.
The new mapped behavior is specifically the upstream `simple`
directory/file resolve-tree output for relocated `~A` / `~B` conflict entries.
