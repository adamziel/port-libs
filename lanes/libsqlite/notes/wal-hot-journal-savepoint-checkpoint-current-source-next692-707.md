# WAL hot-journal checkpoint current-source next692-707

This slice extends the admitted after-current checkpoint receipt chain through next692-next707 as the direct follow-on to integrated next676-next691. It begins from a next691 checkpoint handoff, verifies restart-salt source token, reader-release database digest, page-cache database header, checkpoint-frame schema cookie, commit-generation WAL-index salt, hot-journal absence with page cache, WAL-index salt reader release, and the next692-699 seal. The second block verifies restart-salt database header, reader-release source token, page-cache database digest, schema-cookie WAL-index salt, commit-generation checkpoint frame, hot-journal delete reader release, WAL-index salt page cache, and the final next700-707 seal.

Files:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php` adds next692-next707 methods on the existing consolidated after-current checkpoint receipt helper.
- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext692707Test.php` chains next692 through next707 directly from next691.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next707.php` provides a Application-shaped final-seal example with a self-test.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext692707Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next707.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext692707Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next707.php --self-test
git diff --check
```

Expected focused test result: `1 test files, 79 assertions, 0 failures`.
