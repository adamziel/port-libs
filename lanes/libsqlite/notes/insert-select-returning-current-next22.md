# INSERT SELECT RETURNING current next22

2026-05-27 isolated slice `yield-sqlite-application-migration-import-current-next22`.

- Behavior: `SQLiteInsertSelectSql` now splits top-level `RETURNING` from the SELECT source, applies existing `INSERT ... SELECT` conflict handling, and projects RETURNING rows only for rows actually inserted. `OR IGNORE` conflicts return no row, while `OR REPLACE` returns the replacement insert rows and keeps deleted conflict rows separate.
- Application smoke: `examples/application-insert-select-returning-current-next22.php` previews copied `wp_options` archive/import rows with `INSERT OR IGNORE ... SELECT ... RETURNING` labels before migration tooling trusts the copied database.
- Non-overlap: avoids accepted UPDATE FROM current conflict behavior, INSERT SELECT conflict execution, scalar SELECT subqueries, SELECT SQL text/JOIN/GROUP/subquery/comma-LIMIT/expression ORDER BY, JSON table cursor/source/constraint work, WAL/VFS/B-tree storage clusters, and Unicode GLOB ranges. This slice is limited to INSERT SELECT mutation result projection.
- Dependency closure: no new support component is needed; the implementation reuses the bounded `SQLiteSelectSql` projection executor for RETURNING rows.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteInsertSelectReturningCurrentNext22Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS insert select returning plan separates select and returning SQL
PASS insert select returning ignore exposes only inserted rows
PASS insert select returning replace exposes replacement inserts not deletes
PASS insert select returning projects wildcard rows
PASS insert select returning projects expressions and aliases
PASS insert select returning keeps null unique values returnable
PASS insert select returning empty insert has empty returning rows
PASS insert select returning preserves absent returning metadata without clause
PASS insert select returning rejects empty projection
PASS insert select returning rejects unsupported returning expression

1 test files, 58 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +58 for the new focused PHP assertions once integrated on current accepted HEAD.
