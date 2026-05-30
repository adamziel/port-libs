# DML Trigger RETURNING Conflict Current Source Next106

Adds a bounded current-source DML behavior helper for `INSERT OR REPLACE/IGNORE ... RETURNING`
with row triggers. The slice models the SQLite ordering that matters for copied
Application option imports:

- BEFORE INSERT triggers can retarget the candidate before uniqueness checks and
  before the RETURNING image is captured.
- `OR REPLACE` deletes the current conflicting row before inserting the new row,
  and delete triggers fire only while recursive triggers are enabled.
- AFTER INSERT target mutations update the final row but do not rewrite the
  statement RETURNING row.
- `OR IGNORE` suppresses the conflicting row and emits no RETURNING row.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteDmlTriggerReturningConflictCurrentSourceNext106Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 55 assertions, 0 failures

php lanes/libsqlite/examples/application-dml-trigger-returning-conflict-current-source-next106.php
application-dml-trigger-returning-conflict-current-source-next106 self-test passed
```

Dependency closure: no new support component is needed. The implementation uses
lane-local PHP row-array trigger/conflict primitives and does not require
ext/sqlite, VFS changes, or upstream cache mutation.

Non-overlap: this does not repeat accepted batch102/103 trigger/FK/UPSERT
surfaces, savepoint RETURNING, UPDATE FROM, or prior UPSERT secondary-conflict
checks. It focuses on current-source `INSERT OR REPLACE/IGNORE` conflict
ordering with row triggers and RETURNING image timing.
