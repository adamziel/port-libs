# WAL hot-journal checkpoint current-source next708-723

This slice extends the admitted after-current checkpoint receipt chain through next708-next723 as the direct follow-on to integrated next692-next707. It begins from a next707 checkpoint handoff, verifies restart-salt database digest, reader-release checkpoint frame, page-cache source token, schema-cookie database header, commit-generation WAL-index salt, hot-journal absence with reader release, WAL-index salt page cache, and the next708-715 seal. The second block verifies restart-salt database header, reader-release source token, page-cache database digest, checkpoint-frame schema cookie, commit-generation checkpoint frame, hot-journal delete with page cache, WAL-index salt reader release, and the final next716-723 seal.

Files:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php` adds next708-next723 methods on the existing consolidated after-current checkpoint receipt helper.
- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext708723Test.php` chains next708 through next723 directly from next707.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next723.php` provides a Application-shaped final-seal example with a self-test.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext708723Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next723.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext708723Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next723.php --self-test
git diff --check
```

Expected focused test result: `1 test files, 80 assertions, 0 failures`.
