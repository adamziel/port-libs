# WAL hot-journal savepoint checkpoint current-source next226

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-reset file-state verifier for the WAL hot-journal/savepoint/checkpoint chain. It consumes the accepted next218 restart/truncate admission shape and requires matching database bytes, matching reset WAL bytes, absent hot journal, and durable reset receipts before reopening the WordPress current source.

WordPress smoke:

- `examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next226.php` models a copied `wp_options` import that can reopen readers only after the checkpoint reset files and sync receipts agree.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext226Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next226.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext226Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next226.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +81` focused PASS lines from the next226 test. Mapped upstream coverage is unchanged; this is focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint inventory rather than a new manifest-backed upstream row.

Non-overlap: next226 verifies post-reset file-state receipts after accepted next218 reset admission and avoids next219 checkpoint publication, next218 reset admission, WAL byte truncation, rollback-journal commit/apply, VFS savepoint rollback, and reader-slot validation.

Dependency closure: no new support component is needed; the slice reuses existing digest/file-state modeling, hot-journal absence checks, and durable reset receipt evidence.
