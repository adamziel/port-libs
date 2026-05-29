# schema-alter-trigger-generated-current-source-next133

## Behavior

Adds `SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan`, a focused current-source planner for `ALTER TABLE` generated-column changes that affect triggers. The slice composes the accepted `SQLiteSchemaDdlReparsePlan`, then reports trigger transitions across the schema-cookie change:

- generated columns before/after the ALTER;
- trigger `UPDATE OF`, `NEW`, and `OLD` references;
- missing generated references that become resolved after current-source reparse;
- prepared statement invalidation from stale schema cookies;
- optional table rename after the generated-column add.

WordPress smoke: `examples/wordpress-schema-alter-trigger-generated-current-source-next133.php` covers a copied `wp_options` migration adding `option_value_len` and reparsing a trigger that references `new.option_value_len`.

## Verification

Baseline `phpPass`: 56029.

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaAlterTriggerGeneratedCurrentSourceNext133Test.php
```

Result:

```text
1 test files, 55 assertions, 0 failures
```

Additional checks:

```sh
php -l lanes/libsqlite/src/SQLiteSchemaAlterTriggerGeneratedCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteSchemaAlterTriggerGeneratedCurrentSourceNext133Test.php
php -l lanes/libsqlite/examples/wordpress-schema-alter-trigger-generated-current-source-next133.php
php lanes/libsqlite/examples/wordpress-schema-alter-trigger-generated-current-source-next133.php --self-test
git diff --check -- lanes/libsqlite
```

Expected lane-local `phpPass`: 56084 (+55). No mapped upstream coverage change is claimed.

## Non-Overlap

This avoids accepted next106 generated-trigger reparse snapshots, next117 ALTER generated view/trigger helper, next125 rename reparse, next128 dependent record listing, and next130 generated-index reparse. The new behavior is specifically trigger current-source dependency resolution after generated-column ALTER changes.

## Dependency Closure

No new support component is needed. This reuses native schema DDL reparse, generated-column catalog metadata, and trigger SQL dependency parsing already present in the lane.
