# PRAGMA index_xinfo foreign-key current-source next153 consolidation

## Behavior

Consolidates the direct next153 PRAGMA `index_xinfo` plus `foreign_key_check`
readiness page onto the descriptive production entry point
`foreignKeyRepairReadinessPage()`. The old numbered private helpers for the
same page were renamed to stable descriptive helpers while preserving the
existing operation strings, source modes, result keys, cursor behavior, and
Application example output.

## Evidence

Before edit:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext153Test.php`
- Result: `1 test files, 84 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next153.php --self-test`
- Result: `application-pragma-index-xinfo-foreignkey-current-source-next153 self-test passed`

After edit:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext153Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next153.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext153Test.php`
- Result: `1 test files, 84 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKey*.php`
- Result: `170 test files, 14073 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next153.php --self-test`
- Result: `application-pragma-index-xinfo-foreignkey-current-source-next153 self-test passed`

## Dependency Closure

No new support component is needed. This is production API/helper cleanup over
the existing attached schema catalog, `PRAGMA index_xinfo`, and foreign-key
integrity helpers.

## Non-Overlap

This avoids the root-gate suite-evidence/window blockers already integrated at
`83fdf2feb`, does not alter dashboard/progress files, and does not change the
observable next153 metadata strings that downstream tests assert.
