# schema-ddl-reparse-generated-trigger-current-source

Adds `SQLiteSchemaGeneratedTriggerReparseCurrentSourceNextPlan`, a bounded
current/next schema source comparison for triggers whose `NEW` / `OLD`
references include generated columns after DDL reparse.

WordPress path:
`wordpress-schema-generated-trigger-reparse-current-source.php` models a
copied `wp_options` audit trigger prepared before migration DDL adds generated
columns (`option_value_len`, `option_bucket`) that the trigger body references.
The plan reports the schema-cookie change, generated-column additions/removals,
missing references before/after reparse, and the required reparse decision.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaGeneratedTriggerReparseCurrentSourceTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/wordpress-schema-generated-trigger-reparse-current-source.php --self-test
wordpress-schema-generated-trigger-reparse-current-source self-test passed
```

Non-overlap: avoids accepted ATTACH/temp/WAL trigger view-cache and trigger
source reprepare slices, trigger/FK/UPSERT execution slices, generated-column
catalog CHECK/autoindex parsing, and schema-catalog create/drop/rename
current/next DDL. This slice is narrower: trigger source invalidation when
generated columns referenced by `NEW` / `OLD` become resolvable or disappear
after current-source DDL reparse.

Dependency closure: no new support component is needed. The patch reuses
lane-local schema records and adds a bounded parser/plan for generated-column
metadata plus trigger pseudo-column references.
