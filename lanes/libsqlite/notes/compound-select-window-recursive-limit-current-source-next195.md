# Compound SELECT Window Recursive LIMIT Current Source Next195

- Slice: `compound-select-window-recursive-limit-current-source-next195`
- Base accepted HEAD: `cd4fca46b5c6f85b94a3797534a1b59653071b5f`
- Behavior: parser-level `WITH RECURSIVE` rows feed `row_number()` window output through an `INTERSECT` arm, an `EXCEPT` anti-join arm, and final `ORDER BY pos DESC, id LIMIT/OFFSET` boundary handling.
- Application path: copied `wp_options` autoload rows model option-import current vs next source changes where transient cleanup rows are excluded before final pagination.
- Non-overlap: avoids accepted batch178/next191 compound SELECT DISTINCT/UNION/window recursive LIMIT behavior and queued next192-next194 handoffs by using the `INTERSECT` then `EXCEPT` compound operator chain with row-numbered recursive/table matches.
- Dependency closure: no new support component needed; reuses lane-local `SQLiteSelectSql`, recursive CTE trace, window row-number, compound `INTERSECT`/`EXCEPT`, and final result ordering/limit helpers.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext195Test.php
Focused test run: 1 selected test files (root lock skipped)
63 PASS lines
1 test files, 302 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext195Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext195Test.php

php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next195.php
No syntax errors detected in lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next195.php

php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next195.php --self-test
application-compound-select-window-recursive-limit-current-source-next195 self-test passed

php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/libsqlite
clean
```
