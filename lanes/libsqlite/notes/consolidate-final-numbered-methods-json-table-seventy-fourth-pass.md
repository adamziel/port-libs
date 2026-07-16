# JSON Table Cost Selection Consolidation

Consolidated the direct generated-path rowid cost-selection test/example for the final JSON-table range onto the stable `currentSourceGeneratedPathRowidCostSelectionPlan()` API and stable result keys. The migrated Application smoke now reports the unsuffixed `sqlite-json-table-generated-path-rowid-cost-current-source-selection` dependency instead of a numbered handoff dependency.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceSelectionTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-selection.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceSelectionTest.php`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-selection.php --self-test`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 8 assertions, 0 failures`.

Dependency closure: no new support component needed; this reuses the existing JSON table generated-path rowid planner and removes only the numbered direct caller surface for this migrated slice.
