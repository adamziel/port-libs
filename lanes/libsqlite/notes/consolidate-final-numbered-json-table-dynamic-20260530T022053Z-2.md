# consolidate-final-numbered-json-table-dynamic-20260530T022053Z-2

Consolidated the final JSON-table generated-path rowid cost range test and
WordPress smoke filenames away from the numbered `CurrentSourceNext1017-1048`
surfaces:

- `SQLiteJsonTableGeneratedPathRowidCostFinalRangeEarlyTest.php`
- `SQLiteJsonTableGeneratedPathRowidCostFinalRangeLateTest.php`
- `wordpress-json-table-generated-path-rowid-cost-final-range-early.php`
- `wordpress-json-table-generated-path-rowid-cost-final-range-late.php`

The production entry point remains the canonical
`SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionAlias()`.
Accepted result-array keys, dependency strings, reader policies, replan reason
labels, and numbered cost-selection receipt aliases are intentionally preserved
as observable behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostFinalRangeEarlyTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostFinalRangeLateTest.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-final-range-early.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-final-range-late.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostFinalRangeEarlyTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostFinalRangeLateTest.php` -> `2 test files, 258 assertions, 0 failures`
- JSON-table cost family: `php tools/run-tests.php $(find lanes/libsqlite/tests -name 'SQLiteJsonTableGeneratedPathRowidCost*Test.php' -print | sort)` -> `204 test files, 14405 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-final-range-early.php --self-test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-final-range-late.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This pass reuses the
existing native JSON table generated-path rowid yield guard and cost-selection
alias implementation.
