# JSON Table Numbered Method Consolidation Fifty-third Pass

Consolidated the generated-path rowid cost-selection base surface in
`SQLiteJsonTablePlan`:

- Removed the production numeric suffix from the cost-selection profile,
  cost-class, transition, and reason helpers.
- Replaced the internal numeric cost-selection result keys, replan-reason
  buckets, reader policies, dependencies, cost classes, and replan reasons with
  stable cost-selection names.
- Migrated the direct test and WordPress smoke from numbered filenames to
  descriptive unsuffixed cost-selection files.
- Kept the numeric alias adapter only for downstream still-numbered tests, now
  deriving those aliases from the canonical unsuffixed cost-selection profile.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSelectionTest.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-selection.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSelectionTest.php`
  - `1 test files, 58 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext242Test.php`
  - `1 test files, 58 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-selection.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This pass only renames
existing JSON table planner helper surfaces and reuses the native generated-path
rowid xCurrent/xRowid and cost-selection profiles.
