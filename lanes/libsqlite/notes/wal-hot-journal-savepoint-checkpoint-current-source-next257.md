# WAL hot-journal savepoint checkpoint current-source next257

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, which retires the previous checkpoint current source only after an admitted next253 retry source is durable and visible. The plan requires old reader retirement, retained retry WAL frames, retained retry database pages, hot-journal deletion, checkpoint savepoint close, page-cache sealing, unique receipt names, matching source tokens/generations/digests, exclusive lock evidence, and safe operation ordering.

Application smoke:

- `examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next257.php` models a copied `wp_options` import retry. The recovered checkpoint source is retired only after retry readers have advanced, retry frames remain retained, stale hot-journal/savepoint sidecars are removed, and the page cache is sealed to the retry source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next257.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext257Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next257.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 115 assertions, 0 failures`.

Expected dashboard movement: `phpPass +115` focused PASS lines over the current lane status baseline (`134837 -> 134952`). Mapped upstream coverage remains `674 / 1589`; this is focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint current-source inventory.

Non-overlap: next257 retires the previous checkpoint source after next253 retry-source admission. It does not repeat next253 next-source handoff admission, batch219 next253 hot-journal checkpoint behavior, durable VFS receipt ordering, reopened current-source digest checks, WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, reader snapshot matching, JSON, SELECT, or B-tree behavior.

Dependency closure: no new support component is needed; this reuses next253 next-source admission metadata, reader retirement receipts, WAL retention receipts, hot-journal deletion, savepoint close, and page-cache seal evidence.
