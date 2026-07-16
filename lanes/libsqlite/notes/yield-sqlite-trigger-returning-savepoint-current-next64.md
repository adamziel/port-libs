# Trigger RETURNING savepoint current-next64

Status: focused PHP behavior growth for trigger-driven `RETURNING` rows across a savepoint rollback boundary.

This slice adds `SQLiteTriggerReturningForeignKeySavepointPlan::currentNextYield()`. It composes the existing trigger/FK `RETURNING` executor with savepoint rollback metadata and reports the current attempted row/yield stream separately from the next visible row/yield stream after rollback to the savepoint image.

Application relevance: copied `wp_options` import batches may update option rowids under BEFORE/AFTER triggers while deferred child metadata references are checked at statement end. The smoke shows attempted `RETURNING` rows with trigger-mutated values, then a rollback-to-savepoint boundary where the next visible rowids, metadata rows, dirty pages, and WAL tail return to the savepoint image.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerReturningForeignKeySavepointPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerReturningSavepointCurrentNext64Test.php
php -l lanes/libsqlite/examples/application-trigger-returning-savepoint-current-next64.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningSavepointCurrentNext64Test.php
php lanes/libsqlite/examples/application-trigger-returning-savepoint-current-next64.php
git diff --check -- lanes/libsqlite
```

Focused result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +64, from 23341 to 23405. Mapped upstream coverage remains unchanged because this is a focused native behavior slice and does not claim a new hydrated upstream Tcl denominator unit.

Non-overlap: avoids accepted trigger RETURNING SET DEFAULT FK behavior, recursive trigger RETURNING savepoints, view/UPSERT RETURNING savepoints, savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, B-tree page/freelist/overflow clusters, Unicode GLOB, and batch55 trigger/FK coverage. The new behavior is the explicit current attempted `RETURNING` stream versus next post-savepoint rollback stream for trigger/FK statements.

Dependency closure: no new support component is needed. This reuses existing lane-local native PHP trigger/FK `RETURNING`, page-image, dirty-page, and WAL-frame metadata primitives.
