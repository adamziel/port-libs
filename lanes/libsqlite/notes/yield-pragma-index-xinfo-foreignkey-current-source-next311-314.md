# PRAGMA index_xinfo / foreign-key current-source next311-314

Status: focused PHP behavior growth for `pragma-index-xinfo-foreignkey-current-source-next311-314`.

This slice follows the next307-310 preflight handoff and adds action-specific relationship diagnostics for `PRAGMA foreign_key_list` rows whose `ON UPDATE` or `ON DELETE` action cannot be safely replayed against the child table metadata:

- next311: `ON UPDATE SET NULL` targeting a `NOT NULL` child column.
- next312: `ON DELETE SET NULL` targeting a `NOT NULL` child column.
- next313: `ON UPDATE SET DEFAULT` targeting a child column with no default.
- next314: `ON DELETE SET DEFAULT` targeting a child column with no default.

Validation targets:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext311314Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next311-314.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext307310Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext311314Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next311-314.php --self-test`
- `git diff --check`

Non-overlap: this keeps next307-310 generic SET NULL/SET DEFAULT relationship blockers intact and only layers action-column-specific current-source diagnostics. It does not touch upstream-suite countability rows, progress files, lane status, pager, JSON, B-tree, WAL, VFS, planner, or unrelated PRAGMA surfaces.

Next slice: continue PRAGMA index_xinfo / foreign-key current-source with the next unreconciled action or relationship diagnostics after next314.
