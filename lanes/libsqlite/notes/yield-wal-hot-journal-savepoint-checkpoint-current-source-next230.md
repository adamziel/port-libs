# WAL hot-journal savepoint checkpoint current-source next230

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a reopened-reader ticket fence after accepted next227 publish receipts. It verifies each reopened reader has advanced to the next-source epoch, checkpoint frame/cookie, schema cookie, published page digests, and cannot still see the hot journal or a WAL tail hidden by the checkpoint.

Application smoke:

- `examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next230.php` models copied `wp_options` import readers reopening only after the hot-journal/savepoint checkpoint is sealed and stale WAL tail visibility is fenced.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext230Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next230.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext230Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next230.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +61` focused PASS lines, from `112201` to `112262`. Mapped upstream coverage remains `631 / 1589`; this is focused PHP behavior over existing WAL hot-journal/savepoint/checkpoint inventory rather than a new manifest-backed upstream row.

Non-overlap: next230 validates reopened reader tickets after next227 publish receipts. It avoids next226 file-state receipts, next227 publish receipt sealing, next218 reset admission, WAL byte truncation, rollback-journal commit/apply, VFS savepoint rollback, checkpoint transaction planning, reader checkpoint snapshots, B-tree, JSON, SELECT, and encoding surfaces.

Dependency closure: no new support component is needed; this reuses next227 publish receipts and adds a bounded native PHP reopened-reader ticket fence.
