# SQLite Schema Trigger/View Current-Next73

## Behavior

`SQLiteSchemaCatalogDdlPlan` now accepts schema-qualified DDL names for bounded
catalog current/next planning:

- `CREATE TEMP VIEW temp.name ...`
- `CREATE TEMP TRIGGER temp.name ... ON main.view ...`
- `DROP VIEW main.name`
- `DROP TRIGGER temp.name`
- schema-qualified `CREATE TABLE`, `CREATE INDEX`, and `ALTER TABLE` names

The catalog rows continue to store SQLite's local `name` and `tbl_name` fields,
while the original SQL text is preserved for reparse and trigger bodies with
embedded semicolons remain a single schema statement.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaTriggerViewCurrentNext73Test.php
```

Result:

```text
1 test files, 55 assertions, 0 failures
```

Adjacent DDL regression command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaCatalogDdlCurrentNext56Test.php lanes/libsqlite/tests/SQLiteSchemaDdlReparseCurrentNext56Test.php lanes/libsqlite/tests/SQLiteSchemaTriggerViewCurrentNext73Test.php
```

Result:

```text
3 test files, 221 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-schema-trigger-view-current-next73.php
```

Result: schema version advanced `73 -> 77`, dropped the qualified old
`autoloaded_options` view/trigger, then recreated temp view/trigger rows with
`rootpage` `0` and `tbl_name` normalized to `autoloaded_options`.

## Non-Overlap

This avoids accepted trigger RETURNING/savepoint, attach temp view/trigger,
schema DDL reparse, JSON, WAL, VFS, and B-tree current-next clusters. The slice
is limited to parser/catalog handling for schema-qualified trigger and view DDL
names in current/next schema rows.

## Dependency Closure

No new support component is needed. This reuses lane-local schema catalog DDL
planning, SQLite schema records, and focused PHP TestRunner coverage only.
