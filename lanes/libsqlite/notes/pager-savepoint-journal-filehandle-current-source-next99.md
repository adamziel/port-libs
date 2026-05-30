# pager-savepoint-journal-filehandle-current-source-next99

This slice adds `SQLiteVfsFileWriter::applySavepointRollbackAndBeginNextStatementFromCurrentSourceNext99()`. It composes the accepted current-source savepoint rollback file-handle path with the next pager statement-journal edge: after restoring the current database image and truncating the WAL prefix, the next statement journal is written from the restored current-source page image, not from the stale dirty page.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointJournalFilehandleCurrentSourceNext99Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 54 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pager-savepoint-journal-filehandle-current-source-next99.php --self-test
{
    "status": "pager_savepoint_journal_filehandle_current_source_next99",
    "applied": 12,
    "filesDeleted": 2,
    "databaseBytesAfter": 1536,
    "walBytesAfter": 1104,
    "nextStatementJournal": "wp-content/database/retry.stmt",
    "nextStatementSourcePrefix": "before plugin autoload next99",
    "retryStatementJournalWritten": true,
    "retryStatementJournalFromRestoredSource": true,
    "outerStatementJournalPreserved": true,
    "pluginStatementJournalDeleted": true,
    "childStatementJournalDeleted": true,
    "applicationUse": "Rollback a failed copied wp_options plugin import savepoint from current file handles, then seed the next retry statement journal from the restored current source page."
}
```

Non-overlap: this avoids accepted next94 current-source rollback file-handle application, WAL byte truncation, savepoint page-image rollback, VFS savepoint rollback apply, rollback-journal apply/commit/super-journal, VFS writer/sync/lock clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, B-tree page/freelist/overflow clusters, and Unicode GLOB behavior. The new surface is the next statement-journal file handle seeded from the restored current source after the rollback has been applied.

Dependency closure: no new support component is needed. The slice reuses `SQLiteSavepointStack`, accepted WAL rollback-to-frame bookkeeping, and `SQLiteVfsFileWriter` atomic file-handle operations.
