# pragma-index-xinfo-foreignkey-current-source-next153

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current/next
source cursor that combines `PRAGMA index_xinfo(...)` metadata with scoped
`PRAGMA foreign_key_check(...)` rows. It is intentionally separate from the
accepted rootpage and quickcheck cursors: this slice reports index column
metadata drift (`index_xinfo_drift`) and remaining next-image FK violations
without mixing in integrity/rootpage rows.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next153.php --self-test`
- Result: `wordpress-pragma-index-xinfo-foreignkey-current-source-next153 self-test passed`

Focused test evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- Result: `1 test files, 84 assertions, 0 failures`

Non-overlap: avoids accepted PRAGMA quickcheck/index/FK next138 and rootpage
current-source next144/next147 surfaces by covering only index_xinfo rowset
stability plus FK current/next repair state under one cursor.

Dependency closure: no new support component is needed. The slice reuses the
existing attached schema catalog and bounded foreign-key integrity evaluator.
