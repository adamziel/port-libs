# Compound window frame LIMIT current-source consolidation

Status: consolidated the `SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan`
direct callable surface by replacing the numbered entrypoint and numbered
private helpers with stable descriptive method names.

Direct test/example migration:

- `SQLiteCompoundWindowFrameLimitCurrentSourceNextTest.php`
- `wordpress-compound-window-frame-limit-current-source.php`

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundWindowFrameLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundWindowFrameLimitCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-window-frame-limit-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundWindowFrameLimitCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-compound-window-frame-limit-current-source.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a consolidation
only and reuses the existing SELECT SQL, compound, and window helpers.

Non-overlap: limited to the compound/window frame LIMIT current-source direct
method family. It does not alter STAT4 planner behavior, JSON, WAL, VFS,
B-tree, trigger, attach, PRAGMA, or upstream-suite evidence.
