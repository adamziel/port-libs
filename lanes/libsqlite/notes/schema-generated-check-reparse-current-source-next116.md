# schema-generated-check-reparse-current-source-next116

Status: focused PHP behavior growth for generated-column CHECK constraint schema reparse decisions.

This slice adds `SQLiteSchemaGeneratedCheckReparseCurrentSourceNextPlan::currentNext()`. It compares current and next `sqlite_schema` table records after DDL changes and marks prepared schema views for reparse when generated columns, generated-column CHECK constraints, table CHECK constraints, or generated CHECK references change across a schema-cookie boundary. The parser keeps `CHECK`, `UNIQUE`, and `PRIMARY KEY` text inside generated expressions as literal expression text, while extracting real top-level/table and column CHECK constraints.

WordPress path: `wordpress-schema-generated-check-reparse-current-source-next116.php` models copied `wp_options` metadata where generated slug/length columns gain CHECK constraints after an import or plugin migration. The smoke proves the prepared schema view expires after the cookie changes and reports the generated CHECK additions.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaGeneratedCheckReparseCurrentSourceNext116Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 70 assertions, 0 failures
```

Focused PASS-line delta: `+68`.

Dashboard delta: `phpPass` moves from `44622` to `44690` for the 68 verified PASS lines. `benchmarkDenominator.mapped` is unchanged; this is an additional behavior-backed PHP current-source schema reparse slice over the existing schema/generated-column inventory, not a newly hydrated upstream Tcl row.

Non-overlap: avoids accepted schema generated-trigger reparse current-source next106, ATTACH/temp trigger/view invalidation, schema rename/reparse for triggers/views, JSON generated-index expression handling, PRAGMA table/index analysis, VFS/WAL/B-tree clusters, JSON table constraints, encoding affinity, and runner evidence. This patch is limited to generated-column CHECK constraint catalog deltas on the current-source to next-source schema boundary.

Dependency closure: no new support component is needed. The slice reuses lane-local `SQLiteSchemaRecord` metadata and bounded CREATE TABLE parsing.
