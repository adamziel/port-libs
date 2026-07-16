# Tree Merge No-Merge-Base Diff3 Fixture Parity

Micro-slice: `gitoxide-tree-merge-conflict-fixture-parity-20260602T000823Z`
Base accepted HEAD: `df6aab6c7b87e548fe655763cf42a9438f111f94`

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture script: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Rows: `no-merge-base/A-B-diff3.merge-info` and `no-merge-base/A-B-diff3-reversed.merge-info`

The generated archive records unrelated histories where both sides add
`content`. The diff3 tree contains an explicit empty-base section, stage 2 is
the selected ours blob, stage 3 is the selected theirs blob, and the reversed
merge swaps those side labels.

## Native Delta

- `TreeMergeTest.php` now extends the existing no-merge-base fixture with the
  upstream diff3 body, worktree conflict-file body, index stage order, reversed
  diff3 body, and forced ancestor/ours/theirs content resolutions.
- `fixtures/wordpress-tree-merge.php` and `examples/wordpress-tree-merge.php`
  now include an unrelated-history plugin bootstrap add/add conflict smoke.
- No production source change was needed; the existing native `TreeMerge`,
  `BlobMerge`, and `TreeMergeResult` paths already support this fixture once
  covered.

## Verification

- Before this slice: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  passed `1 test files, 907 assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-tree-merge.php`
- `php -l lanes/gitoxide/examples/wordpress-tree-merge.php`
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  passed `1 test files, 927 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-tree-merge.php` exited `0` and
  reported `unrelated-conflicts=1`, `unrelated-diff3-base=yes`, and an
  ours-resolved bootstrap body.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `41 test files,
  11078 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This deepens the already represented `no-merge-base` fixture by adding the
distinct upstream `A-B-diff3` and reversed diff3 baseline row plus forced
content resolution checks. It does not repeat accepted super-1/super-2,
directory-file, file/tree replacement, change/delete, submodule,
renamed-symlink, type-change-and-renamed, rename-within-rename,
rename-rename-plus-content, rename-add-delete, binary-attribute, same-rename
mode, transport, pack, reference, URL/refspec, config, attributes/pathspec, or
Cargo workspace evidence slices.

## Dependency Closure

No new support component is needed. The slice reuses native PHP tree/blob
objects, `TreeMerge::mergeRecursive()`, `BlobMerge::STYLE_DIFF3`,
`TreeMergeResult::resolveTreeConflicts()`, merge-index stage expansion, and the
existing WordPress tree-merge fixture/example. It does not shell out to Git,
run live network/provider tests, inspect credentials, or require a shared
support-library activation gate.
