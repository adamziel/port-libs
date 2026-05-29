# JSON table numbered helper consolidation seventy-first pass

Consolidated a bounded `SQLiteJsonTablePlan.php` generated-path rowid helper cluster by removing numeric worker suffixes from private helper method names for:

- alias limit planning formerly ending in `207`
- current-source `xCurrent` planning formerly ending in `212`
- `xColumn` value/cost helpers formerly ending in `214`
- yield-cost planning formerly ending in `215`

The public array keys and opcode/cost strings remain unchanged so existing direct tests preserve scenario coverage while production helper identifiers move toward stable descriptive names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasLimitTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext212Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext214Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext215Test.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production helper-name consolidation only.
