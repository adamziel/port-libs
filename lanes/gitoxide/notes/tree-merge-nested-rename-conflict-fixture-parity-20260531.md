# Gitoxide tree merge nested rename conflict fixture parity - 2026-05-31

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Accepted base for this worker: `04e2559bf286c590dfe8ddc3424be7754eff88e2`.
- Targeted upstream files:
  - `gix-merge/tests/fixtures/tree-baseline.sh`
  - `gix-merge/tests/merge/tree/baseline.rs`
  - `gix-merge/tests/merge/tree/mod.rs`
- The `rename-within-rename` fixture has one side rename parent directory `a` to `a-renamed` while the other side renames nested directory `a/sub` to `a/sub-renamed`. The upstream baseline expects both nested directory locations to be represented under the renamed parent and records file-level conflict-index stages for the nested directory rename.

## Native PHP Behavior

- `TreeMerge::mergeRecursive()` now detects a nested directory rename inside a parent directory that was also renamed.
- The merge keeps both nested source and target directories under the renamed parent, using the content-merged target tree for the copied source path.
- The conflict is recorded as `nested-directory-rename`, with ancestor/ours/theirs tree stages preserving the source and renamed nested directory identities.
- `MergeIndexFile::entriesForResult()` expands `nested-directory-rename` tree stages into file-level index entries, matching the upstream fixture's conflict-index shape.
- The WordPress recursive tree merge example now includes a plugin parent-directory rename with an internal `includes` to `src` directory rename, proving the native path keeps both REST-route directory copies visible without writing worktree conflict marker files.

## Verification

- `php -l lanes/gitoxide/src/TreeMerge.php`
- `php -l lanes/gitoxide/src/MergeIndexFile.php`
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
- `php -l lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`: `1 test files, 480 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 4641 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php`: exits `0`
- `git diff --check -- lanes/gitoxide`: exits `0`

## Dependency Closure

No new support component is needed. The slice reuses native tree/blob object storage, recursive tree merge helpers, existing rename detection, and index-stage expansion. The full Cargo workspace runner was not executed because it would hydrate/build the large Gitoxide workspace beyond this isolated micro-slice.

## Non-Overlap

This patch does not repeat accepted attributes/pathspec POSIX boundary behavior, loose-object allocation-limit integrity, or earlier flat directory rename/rename-delete fixture slices. It maps one upstream `gix-merge` tree-baseline fixture: `rename-within-rename`.
