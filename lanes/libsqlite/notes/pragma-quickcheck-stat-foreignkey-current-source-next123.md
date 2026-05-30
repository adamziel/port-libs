# pragma-quickcheck-stat-foreignkey-current-source-next123

Adds `SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield`, a bounded current-source yield for a Application import preflight that combines shallow `PRAGMA quick_check`, `sqlite_stat1`/`sqlite_stat4` catalog readiness, and `PRAGMA foreign_key_check` rows behind one stable source cursor.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePragmaQuickcheckStatForeignKeyCurrentSourceYield.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaQuickcheckStatForeignKeyCurrentSourceNext123Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-quickcheck-stat-foreignkey-current-source-next123.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickcheckStatForeignKeyCurrentSourceNext123Test.php`
- `php lanes/libsqlite/examples/application-pragma-quickcheck-stat-foreignkey-current-source-next123.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This avoids accepted pointer-map/rootpage/FK integrity pagination, foreign-key root integrity next117, index_xinfo/foreign-key current-source next118, quickcheck pointer-map behavior, and schema PRAGMA catalog rows. The new surface is a stat-catalog plus quickcheck plus FK current-source cursor used as a single Application migration preflight.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local PRAGMA quick_check and foreign_key_check primitives and adds a small current-source composer under `lanes/libsqlite/src`.

Next task:

Wire the stat catalog readiness rows into broader ANALYZE/stat planner admission when parser-level migration SQL starts executing these PRAGMA preflights directly.
