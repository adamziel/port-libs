## Attach/Schema Numbered Method Consolidation Fourteenth Pass

This pass removes the direct numbered rollout-window test surface for the
already-consolidated attach/TEMP/WAL schema-cache planner:

- renames the old numbered rollout-window test to
  `SQLiteAttachTempWalSchemaCacheRolloutWindowTest.php`;
- keeps the test on the canonical
  `SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()` entrypoint;
- updates adjacent attach schema-cache notes so they no longer refer to the old
  numbered production class or removed numbered test file.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCachePlan.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheRolloutWindowTest.php
php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReviewWindowTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheRolloutWindowTest.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheReviewWindowTest.php
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This is a naming
consolidation over the existing attach schema-cache planner and focused tests.
