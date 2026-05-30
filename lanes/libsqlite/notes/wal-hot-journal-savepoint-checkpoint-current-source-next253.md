# WAL hot-journal savepoint checkpoint current-source next253

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, which admits a retry/next WAL current source only after an already reopened next249 checkpoint source has matching next-generation receipts. The plan requires advancing source token/generation metadata, next database and WAL digests, dirty-page materialization, commit-frame sync, retry-reader acknowledgements, hot-journal fencing, savepoint release, unique receipt names, and safe handoff ordering.

Application smoke:

- `examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next253.php` models a copied `wp_options` import retry after hot-journal savepoint checkpoint recovery. Retry readers are admitted only when database pages, WAL frames, hot-journal fencing, and savepoint release all match the advancing source token.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next253.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext253Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next253.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 110 assertions, 0 failures`.

Expected dashboard movement: `phpPass +110` focused PASS lines over the current lane status baseline (`131296 -> 131406`). Mapped upstream coverage remains `663 / 1589`; this is focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint current-source inventory.

Non-overlap: next253 admits a retry next-source handoff after next249 reopened visibility. It does not repeat durable VFS receipt ordering, reopened current-source digest checks, WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, reader snapshot matching, JSON, SELECT, or B-tree behavior.

Dependency closure: no new support component is needed; this reuses next249 reopened current-source metadata, VFS write/sync receipts, WAL digest inventory, reader acknowledgements, hot-journal fences, and savepoint release receipts.
