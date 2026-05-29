# WAL hot-journal savepoint checkpoint current-source next231

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-publish WAL-index reopen fence for the hot-journal/savepoint/checkpoint chain.

The plan consumes the accepted next227 publish receipt shape and admits the reopened current source only when WAL-index receipts match the published source token, checkpoint frame, checkpoint/schema cookies, next source epoch, WAL digest, salt/checksum digest, backfill frame, and reader read-mark frames. Stale source tokens, stale readmarks, missing SHM syncs, duplicate receipts, missing published scopes, and unpublished scope receipts hold the current source.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next231.php --self-test`

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext231Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next231.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext231Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next231.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext231Test.php` passed with `1 test files, 80 assertions, 0 failures`.

Expected dashboard movement: `phpPass +80` focused PASS lines from the next231 test. Mapped upstream coverage is unchanged; this is additional focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint inventory rather than a new manifest-backed upstream row.

Non-overlap: next231 verifies reopened WAL-index/readmark receipts after next227 publish receipts. It does not repeat next227 publish sealing, next226 file-state receipts, next218 reset admission, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, or standalone SHM read-mark diagnostics.

Dependency closure: no new support component is needed; the slice reuses next227 publish receipts plus WAL-index salt/checksum/readmark metadata already modeled in lane-local WAL primitives.
