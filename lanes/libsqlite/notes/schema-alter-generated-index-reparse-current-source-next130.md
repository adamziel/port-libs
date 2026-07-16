# schema-alter-generated-index-reparse-current-source-next130

## Behavior

`SQLiteSchemaDdlReparsePlan::apply()` now analyzes `CREATE INDEX` terms against
the current schema image after an earlier DDL statement in the same batch. This
lets an `ALTER TABLE ... ADD COLUMN ... AS (...) VIRTUAL` migration immediately
create an index over the newly admitted generated column, while reporting:

- index terms and expression terms;
- generated-column references used by the index;
- whether the index needs current-source reparse handling;
- missing simple index columns before the generated-column add is visible.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaAlterTableGeneratedIndexReparseCurrentSourceNext130Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 52 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-schema-alter-generated-index-current-source-next130.php --self-test
application-schema-alter-generated-index-current-source-next130 self-test passed
```

## Non-Overlap

This avoids the accepted next126 generated ADD COLUMN CHECK/current-row
admission and next128 dependent view/trigger/star reparse metadata. The new
surface is same-batch generated-column index reparse over the current
`sqlite_schema` image after the generated column has been admitted.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local schema DDL
reparse planner, PRAGMA table_xinfo catalog, generated add-column validation,
and current prepared-statement schema-cookie invalidation primitives.
