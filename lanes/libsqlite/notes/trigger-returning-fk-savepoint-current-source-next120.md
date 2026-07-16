# Trigger RETURNING FK Savepoint Current Source Next120

Status: focused PHP behavior growth for `DELETE ... RETURNING` with row triggers, `ON DELETE` foreign-key actions, and current-source savepoint rollback.

This slice adds `SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan`, a bounded native PHP planner for copied `wp_options` rows where a parent DELETE runs under a statement savepoint. It covers:

- top-level DELETE RETURNING rows captured from OLD parent images;
- BEFORE trigger `RAISE(IGNORE)` suppressing a row before DELETE/RETURNING;
- AFTER trigger `RAISE(ROLLBACK)` suppressing the next-source RETURNING stream and restoring the savepoint image;
- `ON DELETE CASCADE`, `SET NULL`, immediate `RESTRICT`, and deferred `NO ACTION` current/next boundaries;
- rollback page-image, dirty-page, and WAL-frame diagnostics for the savepoint restore path.

Application path: `application-trigger-returning-fk-delete-savepoint-current-source-next120.php` models plugin-import cleanup of copied `wp_options` rows while metadata rows still reference deleted options. The smoke proves deferred FK rollback restores the option rows and suppresses the visible RETURNING stream.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNext120Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNext120Test.php

php -l lanes/libsqlite/examples/application-trigger-returning-fk-delete-savepoint-current-source-next120.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-returning-fk-delete-savepoint-current-source-next120.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNext120Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 60 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-returning-fk-delete-savepoint-current-source-next120.php --self-test
application-trigger-returning-fk-delete-savepoint-current-source-next120 self-test passed
```

Dashboard delta: `phpPass` increases by 60 focused PASS lines, from `46412` to `46472`. Mapped upstream coverage remains `604 / 1589`; this is fresh focused PHP behavior over an already mapped trigger/RETURNING/FK/savepoint surface rather than a newly hydrated Tcl inventory unit.

Non-overlap: avoids accepted DML trigger RETURNING conflict next106, recursive UPDATE RETURNING deferred FK next111, trigger RETURNING FK UPDATE savepoint next74, savepoint-trigger rollback, deferred FK cascade triggers, UPSERT/recursive trigger savepoint clusters, WAL/VFS savepoint byte application, pager hot-journal savepoint work, and schema trigger reparse clusters. The new behavior is parent DELETE RETURNING with `ON DELETE` FK actions and trigger rollback/ignore under the current-source statement savepoint.

Dependency closure: no new support component is needed. The slice reuses lane-local row-array trigger, RETURNING projection, savepoint current/next, FK action, page-image, and WAL-frame diagnostics; it does not require ext/sqlite, provider credentials, upstream binaries, or a new shared dependency row.

Next task: continue only with parser-level trigger/FK execution if it wires these planner semantics into a broader native executor; otherwise pivot to the current higher-yield WAL/pager, SQL planner, JSON planner, B-tree, or encoding buckets.
