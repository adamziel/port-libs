# schema-alter-trigger-generated-current-source

## Behavior

Maintains `SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan`, a focused current-source planner for `ALTER TABLE` generated-column changes that affect triggers, with the old worker-numbered production labels removed. The slice composes the accepted `SQLiteSchemaDdlReparsePlan`, then reports trigger transitions across the schema-cookie change:

- generated columns before/after the ALTER;
- trigger `UPDATE OF`, `NEW`, and `OLD` references;
- missing generated references that become resolved after current-source reparse;
- prepared statement invalidation from stale schema cookies;
- optional table rename after the generated-column add.

Application smoke: `examples/application-schema-alter-trigger-generated-current-source.php` covers a copied `wp_options` migration adding `option_value_len` and reparsing a trigger that references `new.option_value_len`.

## Verification

Baseline `phpPass`: 56029.

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaAlterTriggerGeneratedCurrentSourceTest.php
```

Result:

```text
1 test files, 55 assertions, 0 failures
```

Additional checks:

```sh
php -l lanes/libsqlite/src/SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteSchemaAlterTriggerGeneratedCurrentSourceTest.php
php -l lanes/libsqlite/examples/application-schema-alter-trigger-generated-current-source.php
php lanes/libsqlite/examples/application-schema-alter-trigger-generated-current-source.php --self-test
git diff --check -- lanes/libsqlite
```

No mapped upstream coverage or phpPass increase is claimed for this consolidation-only pass.

## Non-Overlap

This avoids accepted generated-trigger reparse snapshots, ALTER generated view/trigger helper, rename reparse, dependent record listing, and generated-index reparse. The maintained behavior is specifically trigger current-source dependency resolution after generated-column ALTER changes.

## Dependency Closure

No new support component is needed. This reuses native schema DDL reparse, generated-column catalog metadata, and trigger SQL dependency parsing already present in the lane.
