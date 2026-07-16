# UPSERT Trigger RETURNING Conflict Current Next32

Slice: `yield-sqlite-trigger-returning-conflict-current-next32`

This current-source slice extends the bounded native PHP UPSERT trigger/FK
yield helper with optional statement-current UNIQUE constraint validation. The
new behavior checks secondary UNIQUE constraints after BEFORE-trigger rewrites
and again after AFTER-trigger target-row mutations, while preserving SQLite
NULL non-conflict behavior and skipped `DO UPDATE WHERE` rows.

Focused coverage added:

- current secondary UNIQUE conflicts on plain INSERT and DO UPDATE rows;
- conflicts introduced by BEFORE and AFTER trigger `set-new` rewrites;
- repeated same-statement rows where an insert becomes the current row for a
  later update;
- later statement rows conflicting with earlier inserted or updated secondary
  unique values;
- composite UNIQUE constraints with partial NULL non-conflicts;
- malformed UNIQUE constraint list and missing-column guards;
- RETURNING row images continuing to reflect the statement changed row while
  secondary uniqueness is validated against the statement-current rowset.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertTriggerReturningConflictCurrentNext32Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertTriggerForeignKeyYieldCurrentNext23Test.php lanes/libsqlite/tests/SQLiteUpsertTriggerReturningCurrentNext26Test.php lanes/libsqlite/tests/SQLiteUpsertTriggerReturningConflictCurrentNext32Test.php lanes/libsqlite/tests/SQLiteUpsertReturningConflictCurrentTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 268 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-upsert-trigger-returning-conflict-current-next32.php
```

The Application smoke reports copied `wp_options` UPSERT RETURNING rows with
statement-current `option_name`, `slug`, and `slot` uniqueness plus a rejected
current `slug` collision, without requiring ext/sqlite.

Non-overlap:

This does not repeat accepted batch23 UPSERT trigger/FK yield behavior,
accepted batch26 UPSERT trigger RETURNING row-image coverage, prior standalone
UPSERT RETURNING current-conflict checks, or accepted SELECT/JSON/VFS/WAL/B-tree
clusters. The new behavior is specifically the interaction between trigger
rewrites, RETURNING/yield rows, and statement-current secondary UNIQUE
constraints.

Dependency closure:

No new support component is needed. The slice reuses the existing lane-local
row-array UPSERT trigger executor and copied Application option fixtures.
