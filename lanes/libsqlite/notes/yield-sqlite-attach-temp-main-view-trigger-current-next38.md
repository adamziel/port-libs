# yield-sqlite-attach-temp-main-view-trigger-current-next38

Implemented bounded `WHEN` admission for attached/temp/main view-trigger yield
planning. `SQLiteAttachTempViewTriggerYieldPlan` now evaluates simple
OLD/NEW/literal trigger `WHEN` predicates before materializing body operations,
including `=`, `!=`, `<>`, `IS`, `IS NOT`, `AND`, `OR`, and SQLite-style
truthiness for numeric NEW/OLD values.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempViewTriggerYieldCurrentNext38Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures
```

Expected lane-status movement: `phpPass` +52, from 13431 to 13483. Mapped
upstream denominator is unchanged; this is current-source focused behavior.

Application smoke:

```text
php lanes/libsqlite/examples/application-attach-temp-view-trigger-yield-current-next38.php
```

The smoke previews copied Application option imports through TEMP, main, and
attached `INSTEAD OF` view triggers while honoring `WHEN` before yielding
writes, so skipped TEMP imports do not produce phantom native PHP write plans.

Non-overlap:

This avoids accepted temp view trigger name resolution, attach/temp trigger FK
schema resolution, recursive trigger depth, recursive UPSERT conflict yield,
UPSERT trigger/FK yield behavior, VFS/WAL/B-tree storage clusters, JSON table
SELECT sources, and the prior next27 trigger-yield body materialization. The
new behavior is specifically `WHEN` admission before current-source yielded
operations across temp/main/attached trigger search and body-table resolution.

Dependency closure:

No new support component is needed. The slice reuses the existing
`SQLiteAttachedSchemaCatalog`, schema records, and lane-local trigger yield
planner; it does not require ext/sqlite, upstream binaries, provider
credentials, or new root dependency rows.
