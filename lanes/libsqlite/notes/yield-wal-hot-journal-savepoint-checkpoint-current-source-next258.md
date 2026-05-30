# WAL hot-journal savepoint checkpoint current-source next258

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-restart writer-admission fence after next255 restarted readers have reopened on an empty WAL generation. The behavior prevents the first new writer from becoming visible unless it proves a new WAL salt, first-frame sequencing, clean read-mark fences for all reopened readers, exclusive lock ownership, no visible hot journal, closed savepoint scope, and durable sync.

Application smoke:

- `examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next258.php` models a copied Application database after checkpoint/truncate and restarted front-page, plugin-cache, and import readers. It verifies that the next writer starts a fresh WAL generation without reusing stale salt/read-mark state.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext258Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next258.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext258Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next258.php
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 101 assertions, 0 failures`, producing `+101` focused PASS lines over the current lane status baseline (`136435 -> 136536`). Mapped upstream coverage remains unchanged because this is current-source PHP behavior coverage.

Non-overlap: next258 admits the first writer after next255 restarted readers by validating reader fences and new WAL salt/frame receipts. It does not repeat next255 reader reopen admission, next252 post-truncate sealing, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction planning, SELECT, JSON, B-tree, or encoding surfaces.

Dependency closure: no new support component is needed; this reuses next255 restarted-reader admission with native PHP writer receipts for salt transition, first-frame sequencing, read-mark fences, lock state, clean savepoint scope, and durable sync.
