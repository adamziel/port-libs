# Tree Merge Super-1 Diff3/Resolve Fixture Parity

Slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T065359Z`
Base: `cc9294ac19877407e3f202dbdfd54b6a9a8fb67d`

## Upstream Source Truth

- Upstream cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture script: `gix-merge/tests/fixtures/tree-baseline.sh`
- Fixture cluster: `super-1`
- Baseline rows covered here: `baseline super-1 A-B-diff3 A B`, plus the `super-1` `make_resolve_tree ancestor A B`, `make_resolve_tree ancestor B A`, `make_resolve_tree ours A B`, and `make_resolve_tree ours B A` cases.
- Test harness reference: `gix-merge/tests/merge/tree/baseline.rs` conflict-style dispatch for `merge` and `diff3`.

## Lane Delta

- Added a focused PHP fixture parity test to `lanes/gitoxide/tests/TreeMergeTest.php`.
- The new test verifies the upstream `super-1` cyclic rename/content-conflict fixture under diff3 conflict style:
  - `four`, `six`, and `two` retain upstream-style `ours`, `base`, and `theirs` marker labels.
  - The virtual base content follows the upstream rename mapping: `five -> four`, `one -> six`, and `three -> two`.
- The same test verifies resolve-tree behavior:
  - `ancestor` resolution restores the original `main` tree (`five`, `one`, `three`).
  - `ours` resolution for `A/B` keeps branch `A` content at `four`, `six`, and `two`.
  - `theirs` resolution for `A/B`, and `ours` resolution for reversed `B/A`, keep branch `B` content.
- No production source change was needed; existing `TreeMerge`, `BlobMerge::STYLE_DIFF3`, and `TreeMergeResult::resolveTreeConflicts()` behavior already matched this upstream fixture shape.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  - `1 test files, 746 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 7888 assertions, 0 failures`
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
  - `No syntax errors detected in lanes/gitoxide/tests/TreeMergeTest.php`
- `php -r '$files=["lanes/gitoxide/lane-status.json","lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": OK\n"; }'`
  - `lanes/gitoxide/lane-status.json: OK`
  - `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json: OK`
- `git diff --check -- lanes/gitoxide`
  - passed with no output

No example was added or updated, so no example smoke was required.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP tree, blob merge, and merge-index support for a bounded upstream fixture parity check.

## Non-Overlap

This does not repeat accepted `rename-rename-plus-content`, `super-2`, directory-file, submodule, conflicting-rename, type-change, or rename-delete tree-merge fixture slices. It is limited to the upstream `super-1` diff3 and resolve-tree variants that were not explicitly covered in the lane test inventory.

## Next Task

Continue with a different upstream-backed Gitoxide gap, preferably remaining `gix-merge` tree-baseline diff3/resolve-tree fixture variants or a non-overlapping transport/protocol/object-database slice.
