# WAL reader pin current/next65

Micro-slice: `wal-reader-pin-current-next65`

Behavior added:

- `SQLiteWalAppendPlan::readerPinCurrentNext()` models a current WAL reader pinned at an existing read-mark frame while a writer appends later committed and uncommitted frames.
- The current reader keeps its old snapshot and page-count boundary; the next reader reuses an available read-mark slot and advances only to the last committed appended frame.
- Uncommitted append tail frames remain hidden from the next reader, and pages beyond the next committed database size surface bounded reader errors.

WordPress relevance:

- The smoke covers a copied `wp_options` database path where an import updates `siteurl` and appends a plugin option page while an older reader still sees the previous committed snapshot.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderPinCurrentNext65Test.php`
- Result: `1 test files, 58 assertions, 0 failures`

Non-overlap:

- Avoids accepted WAL checkpoint restart/read-mark, savepoint byte truncation, VFS savepoint rollback, rollback-journal commit, WAL checkpoint transaction, and MVCC append-visibility clusters by focusing on read-mark slot transition for current vs next WAL readers after appended commits.

Dependency closure:

- No new support component is needed; this reuses native WAL parsing, append planning, read-mark planning, and reader snapshot helpers already in `lanes/libsqlite/src`.
