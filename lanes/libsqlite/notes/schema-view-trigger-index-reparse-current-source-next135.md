# schema-view-trigger-index-reparse-current-source-next135

Status: focused current-source schema DDL reparse behavior for `CREATE TRIGGER`
records whose bodies read generated columns, generated-column indexes, or views.

## Behavior

- `SQLiteSchemaDdlReparsePlan` now reports trigger-body source tables, source
  views, `INDEXED BY` references, generated-column references, generated-index
  references, view references, and `current_source_reparse` for newly created
  triggers.
- The slice is intentionally scoped to `CREATE TRIGGER` metadata during
  sqlite_schema reparse. It does not repeat accepted `ALTER TABLE ADD COLUMN`
  dependent view/trigger reparse, generated-index view reparse, attach/temp
  schema-cache invalidation, or table rename/rename-column token rewriting.
- Application smoke covers copied `wp_options` migration triggers that audit
  generated-column index reads and generated-column view reads before stale
  prepared import statements resume.

## Verification

Focused test:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaViewTriggerIndexReparseCurrentSourceNext135Test.php
```

Result:

```text
1 test files, 53 assertions, 0 failures
```

Example smoke:

```sh
php lanes/libsqlite/examples/application-schema-view-trigger-index-reparse-current-source-next135.php --self-test
```

Result:

```text
application-schema-view-trigger-index-reparse-current-source-next135 self-test passed
```

## Dashboard Delta

- `phpPass`: +53 focused PASS lines expected after clean integration.
- `benchmarkDenominator.mapped`: unchanged at `606 / 1589`; this is focused
  PHP current-source behavior over an already mapped schema DDL reparse surface.
- Root harness: not run from this isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native sqlite_schema DDL
reparse, trigger parsing, view dependency metadata, generated-column metadata,
and index-term parsing already present in the libsqlite lane.
