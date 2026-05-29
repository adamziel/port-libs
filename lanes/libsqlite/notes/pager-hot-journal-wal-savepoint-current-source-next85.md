# Pager Hot Journal WAL Savepoint Current Source Next85

## Scope

Adds `SQLitePagerHotJournalWalRecoveryPlan::savepointWalRecoveryCurrentSourceNext()` for the pager ordering edge where a hot rollback journal first restores the database image, then the current WAL source is rolled back to a savepoint prefix and checkpointed for the next reader.

This avoids the accepted hot-journal apply, WAL byte-truncation, VFS savepoint rollback, super-journal commit, WAL recovery checkpoint, and batch82 WAL savepoint master-journal surfaces. The new behavior proves the cross-boundary source order: dirty database pages are replaced by rollback-journal pages before retained WAL frames are considered current, while rolled-back WAL savepoint frames disappear before the next reader opens.

## Evidence

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalWalSavepointCurrentSourceNext85Test.php`
- Result: `1 test files, 64 assertions, 0 failures`
- New focused PASS lines: `64`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-pager-hot-journal-wal-savepoint-current-source-next85.php --self-test`

## Dependency Closure

No new support component is needed. This reuses existing native PHP rollback-journal parsing/recovery, WAL parsing/checkpointing, savepoint frame bookkeeping, and pager visibility helpers.

## Next

Follow-up storage work should move to broader pager/VFS transaction application or distinct WAL checkpoint/reset durability, not another hot-journal or savepoint truncation variant.
