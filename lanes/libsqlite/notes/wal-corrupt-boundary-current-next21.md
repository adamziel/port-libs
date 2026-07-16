# WAL Corrupt Boundary Current/Next21

## Behavior

This slice adds `SQLiteWal::corruptRecoveryCurrentNextBoundary()` for raw WAL bytes that cannot be trusted as a normal parsed WAL. It first finds the checksum-valid prefix, trims that prefix to the last committed transaction, then reports current-reader visibility versus the next recovered-reader visibility for selected pages.

The boundary keeps committed pages stable while making valid draft frames and corrupt tail frames non-visible to readers. Covered corruptions include frame checksum mismatch, salt mismatch, truncated tail, and header checksum mismatch.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCorruptBoundaryCurrentNext21Test.php
Focused test run: 1 selected test files (root lock skipped)
50 PASS lines
1 test files, 50 assertions, 0 failures
```

Additional verification:

```text
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteWalCorruptBoundaryCurrentNext21Test.php
php -l lanes/libsqlite/examples/application-wal-corrupt-boundary-current-next.php
php lanes/libsqlite/examples/application-wal-corrupt-boundary-current-next.php --self-test
git diff --check -- lanes/libsqlite
```

## Non-Overlap

Avoided accepted WAL checksum prefix apply, transaction-boundary trimming, WAL byte truncation, savepoint rollback VFS apply, rollback-journal commit/apply, checkpoint transaction, VFS writer, and sync/locking clusters. This slice is a reader-visibility boundary over raw corrupt WAL bytes: current valid-prefix view versus next committed recovered view.

## Dependency Closure

No new support component is needed. The implementation reuses existing native PHP WAL checksum scanning, transaction-boundary trimming, reader snapshot, and checkpoint database image helpers.
