# SQLite trigger recursive RETURNING savepoint current-source next147

- Slice: `trigger-recursive-returning-savepoint-current-source-next147`
- Behavior: recursive INSERT triggers yield RETURNING rows before `ROLLBACK TO`, but rows from the rolled-back current source are suppressed from the admitted current-source stream; the next statement restarts from the savepoint image unless the current source was released.
- Application path: copied `wp_options` imports that retry plugin option rows inside a savepoint can explain which recursive trigger RETURNING rows were yielded, which were discarded by rollback, and which rows are visible to the next import source.
- Non-overlap: this does not repeat accepted recursive view RETURNING next143, row-value conflict savepoint RETURNING next132, trigger UPSERT DO NOTHING next142, savepoint page-image rollback, or pager/VFS rollback application. It composes the accepted recursive trigger primitive into a non-view two-source savepoint current-source handoff.
- Dependency closure: no new support component is needed; this reuses native PHP recursive trigger, RETURNING, and savepoint current-source modeling.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext147Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext147Plan.php

php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext147Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext147Test.php

php -l lanes/libsqlite/examples/application-trigger-recursive-returning-savepoint-current-source-next147.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-returning-savepoint-current-source-next147.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointCurrentSourceNext147Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 68 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-recursive-returning-savepoint-current-source-next147.php --self-test
application-trigger-recursive-returning-savepoint-current-source-next147 self-test passed
```
