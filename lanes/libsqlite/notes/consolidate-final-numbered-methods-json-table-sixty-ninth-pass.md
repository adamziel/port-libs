# JSON Table Numbered Method Consolidation Sixty-ninth Pass

Consolidated the final generated-path rowid cost-selection replay surface for
the `1049-1064` range away from direct numbered test and example names:

- Renamed the focused test to
  `SQLiteJsonTableGeneratedPathRowidCostSelectionReplayTest.php`.
- Renamed the Application smoke to
  `application-json-table-generated-path-rowid-cost-selection-replay.php`.
- Updated both direct callers to use the canonical
  `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionPlan()`
  entry point and stable cost-selection result keys instead of numbered alias
  keys, numbered reader policies, and numbered cost-selection dependencies.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSelectionReplayTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-selection-replay.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSelectionReplayTest.php`
  - `1 test files, 9 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-selection-replay.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This pass reuses the
existing JSON table generated-path rowid yield guard and canonical cost
selection planner.
