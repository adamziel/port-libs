# Tree Merge Same-Rename Different-Mode Reversed Fixture Parity

Micro-slice: `gitoxide-tree-merge-conflict-fixture-parity-20260601T142514Z`

## Upstream Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive entries inspected through `git show`:
  - `baseline.cases`
  - `same-rename-different-mode/A-B.merge-info`
  - `same-rename-different-mode/A-B-reversed.merge-info`

The upstream fixture records a custom expected tree because Git's reversed
side order drops the executable bit on `a-renamed/w`. Gitoxide keeps the custom
expected tree in both side orders: the merged tree keeps `a-renamed/w` and
`a-renamed/x.f` executable, while the conflict index swaps stage 2 and stage 3
mode entries for the reversed merge.

## Native Delta

- `TreeMergeTest.php` now verifies the reversed
  `same-rename-different-mode` fixture row:
  - merged tree path remains `a-renamed`;
  - `w` keeps mode `100755` even when the executable side is "theirs";
  - `x.f` keeps mode `100755` and the clean merged body `1..6`;
  - conflict index entries are stage 2 mode `100644` and stage 3 mode
    `100755`, matching the generated reversed merge-info side order.
- `wordpress-tree-merge.php` and its example now expose a WordPress-shaped
  same-target plugin directory rename with a CLI file executable-mode conflict.

No production source change was required; existing native tree-merge mode
conflict handling already matched this upstream fixture row.

## Verification

- Before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  passed `1 test files, 878 assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-tree-merge.php`
- `php -l lanes/gitoxide/examples/wordpress-tree-merge.php`
- `php lanes/gitoxide/examples/wordpress-tree-merge.php`
  passed and reported `same-rename-mode-conflicts=1`,
  `same-rename-mode-cli-mode=100755`, and
  `same-rename-mode-reverse-cli-mode=100755`.
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  passed `1 test files, 887 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed `40 test files, 9716 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide`
  passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native `Tree`,
`TreeEntry`, `TreeMerge`, `TreeMergeResult`, merge-index stage projection, and
the lane-local in-memory object fixture helpers. Full upstream Cargo workspace
verification remains excluded for this isolated fixture slice.

## Non-Overlap

This does not repeat accepted tree-merge file/tree replacement,
change/delete, submodule, super-1, super-2, rename-within-rename,
rename-add, rename-add-delete, rename-rename-plus-content, or
rename-rename-delete-delete resolve-tree slices. It maps one conservative
additional upstream generated row: `same-rename-different-mode` reversed
custom expected-tree parity.
