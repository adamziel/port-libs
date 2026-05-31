# Tree Merge Renamed Symlink Resolution Parity

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Upstream files:
  - `gix-merge/tests/fixtures/tree-baseline.sh`, lines 1605-1631 in the pinned source.
  - `gix-merge/tests/merge/tree/baseline.rs`, conflict side structures and `ConflictKind::RenameRename`.
- Upstream behavior: in `renamed-symlink-with-conflict`, both sides merge `a/x.f` cleanly while renaming the same symlink to different names. `ResolveWith::Ancestor` keeps the original `link`; `ResolveWith::Ours` for A/B keeps `link-renamed`; the reversed-side expectation is equivalent to resolving A/B as theirs and keeps `link-different`; resolved trees have no remaining index conflict entries.

## Implementation

- Added `TreeMergeResult::resolveTreeConflicts()` with `ancestor`, `ours`, and `theirs` side picks for non-content tree conflicts while leaving content conflicts unresolved unless an explicit content resolution is supplied.
- Added focused assertions to the existing `renamed-symlink-with-conflict` fixture mapping so the PHP tree matches the upstream ancestor and ours resolve-tree expectations.
- Updated the recursive WordPress tree merge example with a mu-plugin symlink rename conflict that resolves to the ours symlink and leaves no index stages.

## Verification

- Red-first focused run before implementation: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed with missing `TreeMergeResult::resolveTreeConflicts()` after 512 assertions.
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`: 1 test files, 527 assertions, 0 failures.
- `php tools/run-tests.php lanes/gitoxide/tests`: 39 test files, 5115 assertions, 0 failures.
- `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`: exited 0 and emitted `symlinkRenameResolution.oursResolvedClean=true` with `indexStagesAfterResolution=0`.
- Full upstream Cargo workspace: not run for this isolated micro-slice.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing in-memory tree/blob object model, recursive tree merge conflict metadata, and object reader/writer callbacks.

## Non-Overlap

This does not repeat the accepted rename-within-rename, conflicting-rename, merge-base, URL/refspec, protocol, transport, or reference transaction slices. The new coverage is specifically the upstream resolve-tree parity for `renamed-symlink-with-conflict`.
