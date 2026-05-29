# Fortieth-pass numbered suffix cleanup

Scope:
- Consolidated the direct `SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan` numbered production method/helper family into descriptive unsuffixed method names.
- Renamed the direct focused test and WordPress smoke away from numbered filenames.
- Removed direct numbered labels from the migrated focused test and smoke output.

Verification:
- `php -l lanes/libsqlite/src/SQLiteCompoundWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundWindowRecursiveLimitCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-window-recursive-limit-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowRecursiveLimitCurrentSourceTest.php` -> `1 test files, 202 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-window-recursive-limit-current-source.php --self-test` -> self-test passed
- `git diff --check -- lanes/libsqlite`
- Banned user-named removed suffix token scan across `src`, `tests`, `examples`, `notes`, and `fixtures` -> no matches

Dependency closure:
- No new support component is needed; this is a production suffix consolidation only.
