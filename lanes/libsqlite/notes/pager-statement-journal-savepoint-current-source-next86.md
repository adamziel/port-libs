# Pager Statement Journal Savepoint Current Source Next86

- Added `SQLiteSavepointStack::rollbackStatementCurrentSourceAndBeginNext86()` for statement-journal rollback inside an active savepoint after verifying the copied database image still matches the current dirty source pages.
- The helper rejects stale, missing, unaligned, or malformed current-source pages before restoring statement preimages and opening the retry statement journal at the retained WAL prefix.
- Added a Application smoke for failed `wp_options` transient insert retry behavior without ext/sqlite.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointCurrentSourceNext86Test.php
php -l lanes/libsqlite/examples/application-pager-statement-journal-savepoint-current-source-next86.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointCurrentSourceNext86Test.php
php lanes/libsqlite/examples/application-pager-statement-journal-savepoint-current-source-next86.php
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 62 assertions, 0 failures` with 62 `TestRunner` PASS lines.

Expected dashboard movement: `phpPass` +62, from 32160 to 32222. Mapped upstream coverage is unchanged at `466 / 1589`.

Non-overlap: this avoids accepted batch66/70 statement rollback/retry metadata, batch75 master-journal statement recovery, batch82 WAL/master-journal recovery, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, and pager cache/master-journal file-handle recovery. The new behavior is the stale-current-source guard and current-source page verification before statement-journal restore.

Dependency closure: no new support component is needed; this reuses the existing bounded `SQLiteSavepointStack` pager/savepoint primitive.
