# yield-sqlite-attach-main-temp-collation-shadow-current-next37

Adds a bounded ATTACH/current-source planner for the SQLite name-resolution edge where an unqualified `wp_options` lookup resolves to `temp.wp_options` before `main.wp_options` or attached schemas. If the temp current source needs an unregistered custom collation, the planner records that usable main/attached indexes are shadowed and cannot be used as fallback.

Dashboard delta: `phpPass` moves from `12903` to `12971` from 68 verified PASS lines. The same focused run reports 70 assertions because two test closures include multiple checks.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachMainTempCollationShadowCurrentNext37Test.php`
- `php lanes/libsqlite/examples/application-attach-main-temp-collation-shadow-current-next37.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteAttachMainTempCollationShadowPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachMainTempCollationShadowCurrentNext37Test.php`
- `php -l lanes/libsqlite/examples/application-attach-main-temp-collation-shadow-current-next37.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted ATTACH temp/VFS open planning, temp view/trigger resolution, temp collation view resolution, PRAGMA metadata, JSON table, VFS writer/lock/sync, WAL, B-tree, SELECT SQL, and Unicode GLOB clusters. This slice is only the main/temp/attached collation-shadow current-source decision.

Dependency closure: no new support component is needed; it reuses the existing schema catalog and schema-record primitives.
