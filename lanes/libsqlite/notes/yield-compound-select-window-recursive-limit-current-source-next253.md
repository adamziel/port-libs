# Compound SELECT Window Recursive LIMIT Current Source Next253

- Slice: `compound-select-window-recursive-limit-current-source-next253`
- Base accepted HEAD: `400990710aba3ff60b53ad81c95572de5d111ae6`
- Behavior: adds a current-source admission fence for parser/executor compound SELECT output where recursive CTE `LIMIT/OFFSET` lineage and window metrics decide the current page before next-source promotion.
- Focused tests: `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Test.php` adds 68 PASS cases over status/dependencies, recursive skipped/emitted/truncated lineage, final current page labels, window metric tokens, exact acknowledgement replay, stale-token rejection, executor parity, non-overlap evidence, and 54 generated Application option-name variants.
- Application smoke: `examples/application-compound-select-window-recursive-limit-current-source-next253.php --self-test` verifies copied `wp_options` current-source exposure before plugin-row next-source promotion.
- Dependency closure: no new support component needed; the patch reuses accepted `SQLiteSelectSql`, compound SELECT, recursive CTE LIMIT/OFFSET, and window output helpers.
- Non-overlap: extends accepted next249 promotion epoch behavior with a current-source admission fence, avoiding accepted next249 epoch-only coverage, next250/next251 row-value/window behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, VDBE, and suite evidence clusters.

Verification to run:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Test.php
php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next253.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Test.php
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next253.php --self-test
git diff --check -- lanes/libsqlite
```
