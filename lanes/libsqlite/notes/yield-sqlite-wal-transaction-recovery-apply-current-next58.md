# WAL transaction recovery apply current-next58

## Behavior

Adds `SQLiteVfsFileWriter::applyWalTransactionRecoveryBoundary()` for the WAL recovery path that must stop at the last committed frame. This differs from checksum-boundary recovery: valid but uncommitted frames after the last commit are discarded from the durable WAL sidecar, while committed frames are checkpointed into the database image.

The slice covers copied WordPress `wp_options` import recovery cases where a process dies after writing a committed WAL transaction and then leaves later draft frames. The next opener should see the committed option update, not the draft `active_plugins` or autoload-index tail.

## Focused evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalTransactionRecoveryApplyCurrentNext58Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 70 assertions, 0 failures
```

New focused PASS lines: 70.

## WordPress smoke

Command:

```sh
php lanes/libsqlite/examples/wordpress-wal-transaction-recovery-apply-current-next58.php --self-test
```

Expected behavior: applies the committed WAL prefix to a copied WordPress database file, truncates the WAL to 1104 bytes, and removes later uncommitted sidecar frames.

## Non-overlap

This avoids the accepted WAL checkpoint transaction, checkpoint durability, savepoint byte truncation, VFS file writer, VFS savepoint rollback, rollback-journal commit, super-journal, sync plan/apply, and hot rollback-journal apply clusters. It uses the existing WAL transaction boundary and adds the missing VFS application for committed-prefix recovery.

## Dependency closure

No new support component is needed. The slice reuses `SQLiteWal::transactionRecoveryBoundary()` and the bounded native PHP `SQLiteVfsFileWriter` operation executor.
