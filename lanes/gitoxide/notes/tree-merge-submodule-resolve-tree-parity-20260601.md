# Tree Merge Submodule Resolve-Tree Parity - 2026-06-01

Micro-slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T054323Z`

Source truth:
- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide`
- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture script: `gix-merge/tests/fixtures/tree-baseline.sh`
- Upstream fixture: `submodule-both-modify`

Behavior ported:
- The native PHP tree merge already staged the submodule commit conflict with stage 1/2/3 entries matching upstream `tree-baseline.sh`.
- This slice adds resolve-tree parity for the same fixture: ancestor resolution selects `e835c0c403c8e494c0ca98f3d25d0b8464c18d38`, ours selects `64466ebdff775ad618d9cc993cf52840e0af528c`, and theirs selects `ea6eb701e03c2497915c25a851f3da8f8e362ca0`.
- The WordPress smoke models the same conflict as a plugin dependency submodule at `wp-content/plugins/acme/vendor/acme-lib`.

Verification:
- Red-first: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` failed before the resolver change with `1 test files, 718 assertions, 1 failures`.
- Focused: `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php` passed with `1 test files, 732 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files, 7644 assertions, 0 failures`.
- Example: `php lanes/gitoxide/examples/wordpress-recursive-tree-merge.php` exited 0 and reported clean ancestor/ours/theirs submodule commit resolutions.
- Lint: `php -l` passed for the changed PHP source, test, and example files.

Non-overlap:
- This slice does not repeat accepted tree-merge rename/delete, rename-add, renamed-symlink, directory-file, type-change-and-renamed, rename-rename-delete-delete, or rename-rename-plus-content fixtures.
- It owns only the `submodule-both-modify` resolve-tree gap.

Dependency closure:
- No new support component is needed. Existing `Tree`, `TreeEntry`, `TreeMerge`, `TreeMergeResult`, and merge-index primitives are reused.
