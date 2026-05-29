# Attach Schema Numbered Method Consolidation Forty-Sixth Pass

This consolidation pass removes three remaining numbered attach-schema
operation/dependency slugs from production code and migrates their direct
tests, WordPress smokes, and notes to stable unsuffixed names.

Changed canonical surfaces:

- `SQLiteAttachDetachTransactionPlan`: `attach-detach-transaction-current`
  and `sqlite-attach-detach-transaction-current`
- `SQLiteAttachWalTempCachePlan`: `sqlite-attach-wal-temp-cache-current`
- `SQLiteAttachWalTempViewCachePlan`:
  `attach-temp-wal-trigger-cache-current-source` and
  `sqlite-attach-temp-wal-trigger-cache-current-source`

Verification:

- `php -l` for changed PHP files
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachTransactionCurrentTest.php lanes/libsqlite/tests/SQLiteAttachWalTempCacheCurrentTest.php lanes/libsqlite/tests/SQLiteAttachTempWalTriggerCacheCurrentSourceTest.php`
- `php lanes/libsqlite/examples/wordpress-attach-detach-transaction-current.php --self-test`
- `php lanes/libsqlite/examples/wordpress-attach-temp-wal-trigger-cache-current-source.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a naming
consolidation over existing attach/schema helpers.

Non-overlap: this pass only touches the direct attach/schema numbered slug
families above. It avoids B-tree, pager, WAL, JSON, planner, trigger
RETURNING, row-value, and upstream-suite consolidation families.
