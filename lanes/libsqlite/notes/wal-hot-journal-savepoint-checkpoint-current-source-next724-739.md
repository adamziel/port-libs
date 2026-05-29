# WAL hot-journal checkpoint current-source next724-739

This slice extends the admitted after-current checkpoint receipt chain through next724-next739 as the direct follow-on to integrated next708-next723. It begins from a next723 checkpoint handoff, verifies restart-salt database digest, reader-release checkpoint frame, page-cache source token, schema-cookie database header, commit-generation WAL-index salt, hot-journal absence with reader release, WAL-index salt page cache, and the next724-731 seal. The second block verifies restart-salt database header, reader-release source token, page-cache database digest, checkpoint-frame schema cookie, commit-generation checkpoint frame, hot-journal delete with page cache, WAL-index salt reader release, and the final next732-739 seal.

Files:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php` adds next724-next739 methods on the existing consolidated after-current checkpoint receipt helper.
- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext724739Test.php` chains next724 through next739 directly from next723.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next739.php` provides a WordPress-shaped final-seal example with a self-test.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext724739Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next739.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext724739Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next739.php --self-test
git diff --check
```
