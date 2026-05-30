# schema-alter-view-trigger-generated-current-source-next128

## Behavior

`SQLiteSchemaDdlReparsePlan::apply()` now reports dependent schema objects that
must be reparsed after `ALTER TABLE ... ADD COLUMN`, including generated-column
adds that change `SELECT *` expansion in views or trigger bodies. The table SQL
rewrite remains bounded to the existing current-source add-column implementation;
the new metadata names affected `index:`, `view:`, and `trigger:` records and
separately identifies `star_expansion_records`.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaAlterViewTriggerGeneratedCurrentSourceNext128Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 52 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-schema-alter-view-trigger-generated-current-source-next128.php --self-test
application-schema-alter-view-trigger-generated-current-source-next128 self-test passed
```

## Non-overlap

This avoids accepted next125 table rename view/trigger SQL rewriting and next126
generated-column CHECK/current-row admission. It adds the narrower unhandled
current-source reparse metadata for `ADD COLUMN` dependent views/triggers,
especially `SELECT *` expansion after a generated column is admitted.

## Dependency Closure

No new support component is needed. This reuses the existing native schema
catalog, add-column corpus, and schema-cookie reparse primitives.
