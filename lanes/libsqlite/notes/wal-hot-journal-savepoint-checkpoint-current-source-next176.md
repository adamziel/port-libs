# WAL hot-journal savepoint checkpoint current-source next176

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next176`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It builds on the accepted hot-journal/savepoint/checkpoint publish path and adds the final reader-reopen gate: the hot rollback journal must have a synced delete receipt for the current checkpoint source, and every reopened reader ticket must point at the next WAL source digest with the savepoint closed and no retained hot-journal digest.

WordPress smoke: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next176.php` models a copied `wp_options` import crash recovery where the hot journal repairs root/autoload pages, a savepoint restores `active_plugins`, checkpoint bytes are published, and an admin reader can reopen only after the journal delete and next-WAL ticket are current.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext176Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next176.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext176Test.php`
  - `1 test files, 50 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next176.php --self-test`
  - `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next176 self-test passed`

Expected dashboard delta: `phpPass` moves from `81770` to `81820` from 50 newly verified focused PASS lines. Mapped upstream coverage remains `613 / 1589`; this is additional behavior-backed current-source WAL/pager coverage under existing WAL/checkpoint inventory rather than a new upstream denominator row.

Non-overlap: avoids accepted WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, super-journal commit, checkpoint transaction planning, next161 cache-token fencing, next166 release lineage, next172 database/WAL sync receipt admission, B-tree, JSON, SELECT, encoding, and suite-runner surfaces. The new surface is specifically reader reopen admission after hot-journal delete and next-WAL source ticket validation.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL parsing, next172 checkpoint publish receipts, savepoint release lineage, and bounded reader-ticket modeling.

Next task: continue with broader WAL/pager transaction application or a distinct durability edge; avoid another reader-ticket wrapper unless it applies a new storage transition.
