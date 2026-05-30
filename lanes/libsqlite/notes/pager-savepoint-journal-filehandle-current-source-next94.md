# Pager Savepoint Journal Filehandle Current Source Next94

Status: focused PHP behavior growth for pager savepoint rollback from current VFS file handles.

This slice adds `SQLiteVfsFileWriter::applySavepointRollbackFromCurrentSourceNext94()`. It hydrates the current database file and optional WAL sidecar from the writer root, applies existing savepoint page-image and WAL-prefix rollback to those current bytes, deletes only statement-journal files discarded by `ROLLBACK TO`, preserves unrelated outer statement journals, and records current-source database/WAL byte provenance.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsFileWriter.php

php -l lanes/libsqlite/tests/SQLitePagerSavepointJournalFilehandleCurrentSourceNext94Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerSavepointJournalFilehandleCurrentSourceNext94Test.php

php -l lanes/libsqlite/examples/application-pager-savepoint-journal-filehandle-current-source-next94.php
No syntax errors detected in lanes/libsqlite/examples/application-pager-savepoint-journal-filehandle-current-source-next94.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointJournalFilehandleCurrentSourceNext94Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 52 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pager-savepoint-journal-filehandle-current-source-next94.php --self-test
{
    "status": "pager_savepoint_journal_filehandle_current_source_next94",
    "applied": 9,
    "filesDeleted": 2,
    "databaseBytesAfter": 1536,
    "walBytesAfter": 1104,
    "restoredPages": [
        2,
        3
    ],
    "discardedStatementJournals": [
        "insert-plugin-child",
        "update-plugin-option"
    ],
    "outerStatementJournalPreserved": true,
    "pluginStatementJournalDeleted": true,
    "childStatementJournalDeleted": true,
    "applicationUse": "Rollback a failed copied wp_options plugin import savepoint from current VFS file handles, restore database pages, truncate the WAL prefix, and delete only stale statement journals while preserving the outer import journal."
}
```

Expected dashboard movement: `phpPass` +52 from focused PASS lines. Mapped upstream coverage is unchanged because this is a narrower VFS/current-source application of already mapped pager savepoint and statement-journal behavior rather than a new upstream inventory unit.

Non-overlap: this avoids accepted savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, statement-journal current-next cleanup, rollback-journal commit/apply, super-journal commit, master-journal hot recovery, WAL checkpoint/read-pin/restart current-source slices, VFS writer/sync/lock/file-control clusters, B-tree page/freelist/overflow/root-collapse clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is current-source file-handle hydration plus stale statement-journal sidecar deletion during savepoint rollback.

Dependency closure: no new support component is needed. The slice reuses lane-local `SQLiteVfsFileWriter`, `SQLiteSavepointStack`, and `SQLiteWal` primitives.

Next task: continue pager/VFS transaction application or checkpoint/savepoint durability from current file handles without repeating accepted savepoint rollback wrappers.
