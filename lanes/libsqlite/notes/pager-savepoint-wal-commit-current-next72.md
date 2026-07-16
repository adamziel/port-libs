# pager-savepoint-wal-commit-current-next72

## Behavior

Adds a bounded pager/WAL commit-current path for a copied Application import:
after `ROLLBACK TO` a current savepoint, the retained WAL prefix is committed,
the transaction stack is closed, discarded savepoint frames stay excluded, and
the next reader sees checkpointed database bytes while the WAL sidecar is
restart/truncate/preserve-mode aware.

This is intentionally distinct from accepted savepoint page-image rollback,
WAL byte truncation, savepoint rollback VFS apply, rollback-journal commit, WAL
checkpoint transactions, and reader-pin handoff slices. The new behavior is
the commit of the retained current prefix after rollback, plus VFS application
of that committed checkpoint state.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointWalCommitCurrentNext72Test.php`
  - `1 test files, 67 assertions, 0 failures`

## Application Smoke

- `php lanes/libsqlite/examples/application-pager-savepoint-wal-commit-current-next72.php`
  - Reports copied `wp_options` import pages with retained `siteurl` WAL
    commit checkpointed, rolled-back plugin frame excluded, WAL sidecar reset,
    and `sqlite-wal-savepoint-commit-current-vfs-apply72` dependency present.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteSavepointStack`, `SQLiteWal`, and `SQLiteVfsFileWriter` primitives.
