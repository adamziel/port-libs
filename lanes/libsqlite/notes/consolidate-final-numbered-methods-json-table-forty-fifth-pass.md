# JSON Table Numbered Method Consolidation Forty-Fifth Pass

Consolidated two remaining numbered JSON-table rowid/hidden-path production
entry methods into stable descriptive names on `SQLiteJsonTablePlan`:

- `currentSourceRowidHiddenPathPlan()`.
- `currentSourceHiddenRowidPathPlan()`.

Direct tests, WordPress examples, notes, dependency markers, and scenario names
were migrated to unsuffixed names. The two local replan-reason payload keys
were also renamed to descriptive rowid/hidden-path names in the direct tests
and examples.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenPathCurrentSourceTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidPathCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-rowid-hidden-path-current-source.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-hidden-rowid-path-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenPathCurrentSourceTest.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidPathCurrentSourceTest.php`
  - `2 test files, 127 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-rowid-hidden-path-current-source.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-hidden-rowid-path-current-source.php --self-test`

Dependency closure: no new support component needed; this pass only renames
existing JSON-table planner entry points and keeps their current native PHP
behavior covered by the migrated focused tests and examples.
