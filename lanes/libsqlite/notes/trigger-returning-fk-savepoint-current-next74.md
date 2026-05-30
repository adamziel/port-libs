# Trigger RETURNING FK Savepoint Current/Next 74

Status: focused PHP behavior growth for trigger `RETURNING` streams where parent-key updates run inside a statement savepoint and foreign-key actions decide the current/next row images.

- Added `SQLiteTriggerReturningFkSavepointCurrentNext74Plan` for bounded UPDATE execution over copied `wp_options`-style parent rows and metadata child rows.
- Covers `ON UPDATE CASCADE`, `SET NULL`, immediate `NO ACTION` rollback, deferred `NO ACTION` release with violation evidence, BEFORE trigger `set-new`, BEFORE trigger `RAISE(IGNORE)`, AFTER trigger `RAISE(ROLLBACK)`, committed RETURNING rows, and attempted current-yield diagnostics suppressed by savepoint rollback.
- Added `application-trigger-returning-fk-savepoint-current-next74.php` as a copied Application smoke showing option id rekeying, metadata cascade, and RETURNING old/current key diagnostics.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningFkSavepointCurrentNext74Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures
```

Non-overlap: this avoids accepted batch68/73 trigger RETURNING savepoint and trigger UPSERT savepoint behavior, prior trigger/FK RETURNING without savepoint rollback, deferred FK cascade corpora, recursive trigger RETURNING, view/UPSERT RETURNING, WAL/VFS savepoint byte application, and pager savepoint current/next clusters. The new surface is FK action/violation handling at the trigger RETURNING statement-savepoint boundary.

Dependency closure: no new support component is needed. The slice reuses lane-local PHP row-array trigger, RETURNING projection, savepoint current/next, and foreign-key action modeling; it does not require ext/sqlite, upstream binaries, provider credentials, or a new shared dependency row.

Next task: continue with a non-overlapping trigger executor gap only if it adds parser-level SQL execution or a larger upstream-backed current/next behavior. Otherwise pivot back to WAL/pager, SQL planner, JSON planner, B-tree, or encoding buckets.
