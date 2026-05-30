# trigger-upsert-returning-recursive-current-source-next

Adds `SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan`, a focused
current-source savepoint model for recursive trigger UPSERT `RETURNING`.

The slice covers a Application `wp_options` import shape where a current source
UPSERT recursively creates child option rows and yields `RETURNING` rows, then a
savepoint barrier rolls back those attempted current-source rows before the next
source starts. The next source is proven to restart from the savepoint image and
to replay recursive child inserts instead of seeing the rolled-back current
image.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/application-trigger-upsert-returning-recursive-current-source-next.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextTest.php
php lanes/libsqlite/examples/application-trigger-upsert-returning-recursive-current-source-next.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 60 assertions, 0 failures`.

Non-overlap: avoids accepted next126 recursive UPSERT RETURNING drain/handoff,
next129 UPSERT RETURNING savepoint rollback, next142 DO NOTHING RETURNING
savepoint behavior, deferred FK RETURNING barriers, row-value RETURNING
savepoint clusters, WAL/VFS savepoint rollback, JSON table, B-tree, encoding,
planner, and PRAGMA accepted surfaces. The new behavior is specifically
recursive trigger UPSERT `RETURNING` rows attempted in the current source,
suppressed by a savepoint barrier, with the next source restarted from the
savepoint image.

Dependency closure: no new support component is needed. This reuses the
existing bounded native PHP recursive UPSERT conflict-yield primitive and adds
a lane-local savepoint/current-source barrier wrapper.
