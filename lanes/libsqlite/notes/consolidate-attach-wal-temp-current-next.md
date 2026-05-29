# SQLite ATTACH WAL/temp current-next consolidation

Consolidated the numbered `SQLiteAttachWalTempCurrentNext65Plan` commit variant and
`SQLiteAttachWalTempCurrentNext68Plan` rollback variant into the canonical
`SQLiteAttachWalTempCurrentNextPlan` production class.

The canonical class preserves both accepted entry points:

- `plan()` for attached main/temp/WAL commit routing.
- `rollbackPlan()` for aborted attached transaction rollback routing.

Focused evidence:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempCurrentNext65Test.php lanes/libsqlite/tests/SQLiteAttachWalTempCurrentNext68Test.php
# 2 test files, 159 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-attach-wal-temp-current-next65.php --self-test
# wordpress-attach-wal-temp-current-next65 self-test passed

php lanes/libsqlite/examples/wordpress-attach-wal-temp-current-next68.php --self-test
# wordpress-attach-wal-temp-current-next68 self-test passed
```

Dependency closure: no new support component is needed; this reuses the existing
attached pager transaction routing and attached transaction rollback routing
models under one canonical production class.
