# Tree Merge Rename/Rename Delete/Delete Resolve-Tree Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T010904Z`

Source truth:
- Upstream commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-merge/tests/fixtures/tree-baseline.sh` case `rename-rename-delete-delete`.
- `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar` generated `resolve-A-B-with-ancestor.tree`, `resolve-B-A-with-ancestor.tree`, `resolve-A-B-with-ours.tree`, and `resolve-B-A-with-ours.tree`.

Behavior ported:
- Reciprocal rename/delete collisions where side A renames `foo -> baz` while deleting `bar`, and side B renames `bar -> baz` while deleting `foo`, still produce the upstream content conflict at `baz`.
- Content-conflict resolution now carries same-target divergent rename source entries so `ancestor` restores both original base paths (`bar`, `foo`) instead of dropping the collided target into an empty tree.
- `ours`/`theirs` content resolution keeps selecting the respective collided `baz` entry, including reversed A/B order.
- The WordPress recursive merge example now includes the same shape for two `wp-content/mu-plugins/*-loader.php` files renamed to one `shared-loader.php`.

Red/green evidence:
- Red first: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed in `maps upstream gix-merge tree-baseline rename-rename-delete-delete fixture shape`, expected `['bar', 'foo']`, actual `[]`.
- After implementation: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed `1 test files, 645 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 6609 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php` passed and reports ancestor-resolved `bar-loader.php` plus `foo-loader.php` with no remaining index stages.
- PHP lint passed for `TreeMerge.php`, `TreeMergeResult.php`, `TreeMergeTest.php`, and `wordpress-recursive-tree-merge.php`.

Dependency closure:
- No new support component is needed. This reuses the existing native tree merge, tree conflict resolution, in-memory object store, and WordPress recursive merge example paths.

Non-overlap:
- This is distinct from the accepted conflicting-rename, conflicting-rename-2, conflicting-rename-complex, renamed-symlink, type-change-and-renamed, and directory-file resolve-tree fixture slices. It only adds upstream `rename-rename-delete-delete` resolve-tree parity.
