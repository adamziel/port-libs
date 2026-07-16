# Gitoxide tree merge multiple merge-bases diff3/resolve-tree fixture parity - 2026-06-01

## Source truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture script: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Fixture case: `multiple-merge-bases`
- Mapped rows: `multiple-merge-bases/A-B-diff3.merge-info`,
  `resolve-A-B-with-ancestor.tree`, `resolve-A-B-with-ours.tree`,
  `resolve-B-A-with-ours.tree`, and the equivalent forced theirs resolution.

## Native delta

- `TreeMergeTest.php` now verifies the upstream multiple-merge-bases diff3
  conflict body after virtual merge-base construction. The diff3 base hunk is
  the contracted virtual-base-only `6` line, while index stages retain the full
  virtual base body.
- The same test verifies forced ancestor/ours/theirs conflict resolution and
  the reversed-side ours result so the PHP resolver matches the generated
  resolve-tree fixtures.
- `wordpress-tree-merge.php` fixture/example now includes a virtual-base
  content migration conflict and an ours resolution smoke for Git-backed
  WordPress content review workflows.

## Verification

- Baseline focused command before patch: `php tools/run-tests.php
  lanes/gitoxide/tests/TreeMergeTest.php` passed `1 test files, 772
  assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/TreeMergeTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-tree-merge.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-tree-merge.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed `1
  test files, 795 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-tree-merge.php` passed and reported
  `virtual-base-conflicts=1` plus the ours-resolved `renamed-content` body.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"),
  true, 512, JSON_THROW_ON_ERROR);
  json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"),
  true, 512, JSON_THROW_ON_ERROR); echo "json-ok\n";'` passed.
- `git diff --check -- lanes/gitoxide` passed.
- Status movement: `phpPass` `8406 -> 8407`; mapped coverage `1778 / 2886 ->
  1779 / 2886`.

## Dependency closure

No new support component is needed. This reuses native tree/blob object
storage, `TreeMerge::mergeRecursiveWithVirtualBase()`, diff3 blob merge
markers, `TreeMergeResult::resolveTreeConflicts()`, and existing merge-index
stage expansion. Full upstream Cargo workspace runner was not executed for this
isolated fixture-parity slice.

## Non-overlap

This does not repeat accepted `super-1` diff3/resolve-tree,
`rename-rename-plus-content`, `change-and-delete`, `directory-file`,
`submodule`, `renamed-symlink`, `conflicting-rename`, or
`type-change-and-renamed` tree-merge slices. It owns only the upstream
`multiple-merge-bases` diff3 and resolve-tree fixture parity gap.
