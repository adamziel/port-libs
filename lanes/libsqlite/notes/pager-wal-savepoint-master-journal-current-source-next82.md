# Pager WAL Savepoint Master-Journal Current Source Next82

Status: focused PHP behavior growth for WAL savepoint replay after master-journal membership changes.

This slice adds `SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext()`. It composes the accepted master-journal cache recheck with the accepted WAL hot-journal savepoint replay, but makes the replay decision use the next master-journal source rather than stale current membership. This covers a Application import crash path where the current opener cached a master journal containing `.ht.sqlite-journal`, a later opener no longer sees that master-journal member, and rollback must skip hot-journal recovery while still truncating savepoint WAL frames.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointReplayPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalSavepointMasterJournalCurrentSourceNext82Test.php`
- `php -l lanes/libsqlite/examples/application-wal-savepoint-master-journal-current-source-next82.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointMasterJournalCurrentSourceNext82Test.php`
- `php lanes/libsqlite/examples/application-wal-savepoint-master-journal-current-source-next82.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses existing native PHP rollback-journal parsing, WAL frame/checksum parsing, savepoint stack frame tracking, and master-journal cache planning.

Non-overlap: avoids accepted super-journal commit, hot rollback-journal apply, WAL byte truncation, savepoint page-image rollback, VFS file-writer, and master-journal cache-only coverage. The added behavior is the current-source handoff between master-journal membership and WAL savepoint replay.
