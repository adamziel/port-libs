Tree-merge rename-delete same-side fixture parity, 2026-06-01
================================================================

Slice
-----

- Worker: `gitoxide-tree-merge-conflict-fixture-parity-20260601T182130Z`.
- Base: `46132b002aae86d77139b7f5e361edf24e0035ba`.
- Scope: `lanes/gitoxide/**` only.

Source truth
------------

- Upstream Gitoxide commit:
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Static upstream files inspected:
  `gix-merge/tests/fixtures/tree-baseline.sh` and
  `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`.
- Mapped fixture rows: `rename-delete/A-similar` and
  `rename-delete/B-similar`.

Behavior mapped
---------------

- Same-side merge recursion over the upstream `rename-delete` similar fixture
  shapes remains clean and produces no conflict/index/worktree conflict entries.
- The A-side fixture preserves the modified `foo` and renamed
  `newdir/{a,b,c}` tree.
- The B-side fixture preserves the moved/modified `bar` inside
  `olddir/{a,bar,b,c}`.
- The WordPress tree-merge fixture/example maps the same deployment shape with
  a plugin directory rename on one side and plugin entry file move/delete on
  the other side.

Verification
------------

- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
  - Passed: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-tree-merge.php`
  - Passed: no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-tree-merge.php`
  - Passed: no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  - Passed: `1 test files, 907 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  - Passed: `40 test files, 10454 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-tree-merge.php`
  - Passed: prints clean same-side A/B tree-merge rows.
- `git diff --check -- lanes/gitoxide`
  - Passed.

Expected movement
-----------------

- `phpPass`: `10434 -> 10454` (`+20` assertions).
- Conservative mapped coverage: `1810 / 2886 -> 1811 / 2886`, if the
  integrator counts the same-side `rename-delete` generated fixture row.

Dependency closure
------------------

- No new support component is needed. This slice reuses the existing native
  PHP `Tree`, `TreeMerge`, object read/write closures, and WordPress
  tree-merge fixture support.

Exclusions
----------

- Full upstream Cargo workspace was not executed.
- Root PHP harness was not run for this isolated micro-slice.
