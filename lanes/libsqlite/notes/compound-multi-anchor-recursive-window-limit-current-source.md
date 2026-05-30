# compound-multi-anchor-recursive-window-limit-current-source

- Behavior: adds current-source coverage for recursive CTEs whose non-recursive side is itself a compound anchor (`UNION` plus `EXCEPT`) before the recursive `UNION` arm, feeding window-function compound SELECT arms with final `ORDER BY` / `LIMIT` / `OFFSET`.
- Application path: copied `wp_options` autoload staging rows model an import/rewrite preview where newly admitted options change the final limited windowed compound output.
- Non-overlap: avoids accepted recursive comma-LIMIT/window compound behavior, accepted LIMIT/OFFSET window compound behavior, JSON table cursor/source/constraint work, VFS/WAL rollback/checkpoint writers, B-tree overflow/freeblock/root-collapse/page-move work, UTF-16/Unicode GLOB slices, and suite-runner evidence patches.
- Dependency closure: no new support component is needed; this reuses native parser/executor, compound SELECT, recursive CTE, and window-function components already under `lanes/libsqlite/src`.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceTest.php
Focused test run: 1 selected test files (root lock skipped)
67 PASS lines
1 test files, 209 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-compound-multi-anchor-recursive-window-limit-current-source.php --self-test
application-compound-multi-anchor-recursive-window-limit-current-source self-test passed
```
