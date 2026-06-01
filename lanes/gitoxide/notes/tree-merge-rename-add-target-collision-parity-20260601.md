# Gitoxide Tree Merge Rename-Add Target Collision Parity

Micro-slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T115423Z`

Upstream source truth:

- Pinned Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Files: `gix-merge/tests/fixtures/tree-baseline.sh`,
  `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`, and
  `gix-merge/tests/merge/tree/baseline.rs`
- Fixture rows: `rename-add/A-B.merge-info`,
  `rename-add/A-B-reversed.merge-info`,
  `rename-add/A-B-diff3.merge-info`, and
  `rename-add/A-B-diff3-reversed.merge-info`

Behavior delta:

- Before this slice, the PHP port reported `rename-add` as two conflicts:
  `foo` `rename-modify` plus `bar` `rename-target-add`, and preserved `foo` in
  the merged tree.
- Upstream generated fixture output records only `bar`: the target-path blob is
  written with conflict markers, the index has only stage 2 and stage 3 entries
  for `bar`, and the source path `foo` is consumed.
- `TreeMerge::renameConflicts()` now attempts the existing blob/blob
  `tryMergeRenameTargetAdd()` path for target collisions even when the
  non-renaming side also modified the original source path. The source-modified
  form intentionally does not attach ancestor entries, matching upstream's
  target-path add/add conflict shape.

Focused evidence:

- Red-first:
  `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed at
  `maps upstream gix-merge tree-baseline rename-add fixture shape` with actual
  tree names `['foo']` instead of expected `['bar']`.
- After fix:
  `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed
  `1 test files, 826 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-tree-merge.php` exited 0 and reported
  `rename-add-conflicts=1` plus `rename-add-entry=review-widget.php`.
- PHP lint passed for:
  `lanes/gitoxide/src/TreeMerge.php`,
  `lanes/gitoxide/tests/TreeMergeTest.php`,
  `lanes/gitoxide/fixtures/wordpress-tree-merge.php`, and
  `lanes/gitoxide/examples/wordpress-tree-merge.php`.
- JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide` passed.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `TreeMerge`, `BlobMerge`, merge-index, and WordPress fixture support.

Non-overlap and follow-up:

- This slice deepens the existing gix-merge tree-baseline mapping without
  moving conservative mapped coverage: `1789 / 2886` remains unchanged.
- It is distinct from the accepted `rename-add-delete`,
  `rename-add-symlink`, `renamed-symlink`, `conflicting-rename`,
  `rename-rename-plus-content`, and `rename-rename-delete-delete` slices.
- Follow-up: upstream `rename-add-symlink` target type-clash relocation remains
  a separate tree-merge fixture parity surface.
