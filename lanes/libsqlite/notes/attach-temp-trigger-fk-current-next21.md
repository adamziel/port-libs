# Attach/temp trigger FK current-next21

Slice: `yield-sqlite-attach-temp-trigger-fk-current-next21`

This focused slice extends `SQLiteAttachTempViewTriggerResolution` with
schema-aware foreign-key context for triggers. It covers the SQLite rule that
foreign-key parent tables resolve in the child table's schema, even when a TEMP
trigger is pinned to a `main` table while its unqualified trigger body writes to
TEMP tables, and when attached schemas have same-named `wp_sites` /
`wp_options` tables.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempTriggerForeignKeyCurrentNext21Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-attach-temp-trigger-fk-current-next21.php --self-test
application-attach-temp-trigger-fk-current-next21 self-test passed
```

Syntax:

```text
php -l lanes/libsqlite/src/SQLiteAttachTempViewTriggerResolution.php
php -l lanes/libsqlite/tests/SQLiteAttachTempTriggerForeignKeyCurrentNext21Test.php
php -l lanes/libsqlite/examples/application-attach-temp-trigger-fk-current-next21.php
```

## Delta

- `phpPass`: `7262 -> 7318` for the 56 verified focused PASS lines in this
  isolated worktree.
- `benchmarkDenominator.mapped`: `457 -> 458` for one newly mapped focused
  schema/trigger FK evidence row.
- Root harness: not run; isolated micro-slice.

## Non-overlap

This does not repeat the accepted attach/temp view-trigger resolution, FK
cascade trigger effects, PRAGMA foreign-key-list, temp schema handling, JSON,
WAL/VFS, B-tree, SELECT SQL, Unicode GLOB, or rollback-journal clusters. The
new behavior is specifically schema-local FK context over trigger targets and
trigger-body dependencies across TEMP, main, and attached schemas.

## Dependency closure

No new support component is needed. The slice reuses the existing attached
schema catalog and trigger metadata resolver.
