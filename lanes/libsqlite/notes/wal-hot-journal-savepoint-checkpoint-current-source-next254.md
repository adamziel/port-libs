# WAL hot-journal savepoint checkpoint current-source next254

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next254`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext254Plan`. It composes the accepted next250 cache/readmark invalidation fence with a new current-source lease admission layer for prepared schema statements, table root pages, index root pages, and read transactions after hot-journal recovery plus savepoint checkpoint publication.

WordPress smoke: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next254.php` covers copied `wp_options` import retry behavior where schema/table/index/read-transaction leases are reusable only when they cite the same source token, generation, schema cookie, database digest, page-cache digest, checkpoint frame, committed WAL frames, reopened readers, and cache-fence receipts.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext254Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext254Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next254.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext254Test.php`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next254.php --self-test`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 105 assertions, 0 failures`, adding 105 focused PASS lines in `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext254Test.php`. Expected dashboard delta: `phpPass` moves from `131296` to `131401`; mapped upstream coverage is unchanged because this is focused WAL/pager current-source behavior over existing mapped hot-journal/savepoint/checkpoint inventory.

Non-overlap: avoids accepted next249 reopen checks, next250 cache invalidation/readmark refresh, WAL byte truncation, VFS writer/sync/lock application, rollback-journal apply/commit, checkpoint transaction planning, WAL restart/truncate reader snapshots, JSON table, SELECT, encoding, and B-tree behavior. The new behavior is specifically post-cache-fence lease admission for statements, root pages, and read transactions before a checkpoint current source can be reused.

Dependency closure: no new support component is needed. The slice reuses lane-local cache-fence receipts, checkpoint source tokens, reader names, root-page lease metadata, and WAL commit-frame inventories.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another cache invalidation wrapper unless it applies a new current-source lease or durability rule.
