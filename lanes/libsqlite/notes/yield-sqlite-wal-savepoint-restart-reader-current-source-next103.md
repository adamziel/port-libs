# WAL Savepoint Restart Reader Current Source Next103

## Behavior

Adds `SQLiteWalSavepointCheckpointPlan::savepointRestartAppendReaderCurrentSourceNext()` for the WAL timeline where a failed WordPress import rolls back to a savepoint, verifies the current WAL source bytes, keeps the current reader on the retained prefix, releases the reader so restart/truncate can reset the WAL generation, and appends a retry transaction for the next reader.

This is narrower than accepted batch99 savepoint reader checkpoint behavior. Batch99 covered pinned/released checkpoint current-source evidence; this slice adds the post-release restart/truncate plus retry append current/next source transition in one behavior surface.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointRestartReaderCurrentSourceNext103Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures
```

Smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-savepoint-restart-reader-current-source-next103.php --self-test
wordpress-wal-savepoint-restart-reader-current-source-next103 self-test passed
```

## Dashboard Delta

Expected focused PASS-line movement is `+62` for the new lane-scoped test file, with `0` failures. Mapped coverage is unchanged because this is additional current-source WAL/savepoint behavior under the already mapped WAL inventory.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP WAL parser, savepoint stack, durable checkpoint planner, WAL append transaction writer, and current-source verification helpers.

## Non-Overlap

Avoids accepted WAL savepoint byte truncation, WAL reader-pin restart/truncate handoff, WAL checkpoint transactions, VFS savepoint rollback application, rollback-journal commit/apply, batch90 current-source pinned-reader coverage, batch94 reader-release checkpoint coverage, and batch99 savepoint reader checkpoint current-source evidence. The new behavior is the released restart/truncate plus retry append source transition.
