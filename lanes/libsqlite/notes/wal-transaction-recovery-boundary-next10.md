# WAL Transaction Recovery Boundary Next10

This slice adds `SQLiteWal::transactionRecoveryBoundary()` for WAL recovery
after checksum-prefix validation. It trims the valid prefix to the last
complete committed transaction, so valid draft frames after the last commit and
corrupt/truncated tail frames are not exposed as checkpointable state.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalTransactionRecoveryCorpusTest.php`
- Result: `1 test files, 66 assertions, 0 failures`
- PASS-line delta: `+66`

Application smoke:

- `php lanes/libsqlite/examples/application-wal-transaction-recovery-boundary.php --self-test`
- Result: self-test passed and reported a copied `wp_options` WAL sidecar with
  `3` valid frames, `2` committed frames, one valid draft frame discarded, one
  corrupt tail frame discarded, and checkpoint bytes containing only committed
  data.

Non-overlap:

- Avoids accepted WAL checkpoint transaction planning, WAL byte truncation,
  VFS file writer/apply, rollback-journal recovery/commit, savepoint image
  rollback, and checksum-only corrupt WAL prefix recovery. This focuses on the
  transaction boundary between a valid checksum prefix and the last complete
  commit frame.

Dependency closure:

- No new support component is required. The slice reuses the existing WAL
  parser/checksum and checkpoint image helpers.
