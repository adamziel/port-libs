# WAL hot-journal savepoint checkpoint current-source next212

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next212`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It models a PASSIVE checkpoint after hot-journal recovery, savepoint rollback, and next209 writer admission. A current reader pin limits checkpoint progress to that reader's end frame, preserves WAL bytes, and forbids restart/truncate-style reset while stale readers are reopened against the current source.

WordPress smoke: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next212.php` covers a copied `wp_options` import where a recovered hot journal and plugin savepoint rollback are followed by a PASSIVE checkpoint while a current options reader is still open.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext212Test.php`
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext212Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next212.php`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next212.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `101605` to `101673` from 68 newly passing focused PASS lines. Mapped upstream coverage remains `622 / 1589`; this is focused WAL current-source behavior over existing hot-journal/checkpoint/savepoint inventory rather than a fresh manifest row.

Non-overlap: this avoids accepted next209 writer fences, next206 statement consumers, next138/142/146 restart/truncate checkpoint reset, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, WAL file writing, and the accepted batch189 WAL writer-fence surface. The new surface is specifically PASSIVE checkpoint partial-progress admission after current-reader pinning.

Dependency closure: no new support component is needed. The slice reuses next209 writer generation metadata, current-source digests, and reader end-frame pin metadata.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another writer-fence or restart/truncate wrapper.
