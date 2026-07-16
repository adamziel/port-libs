# WAL Savepoint Reader Checkpoint Current Source Next94

## Behavior

Adds `SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReleaseCurrentSourceNext()` for the savepoint-reader checkpoint handoff after a `ROLLBACK TO` truncates the WAL to a retained savepoint prefix.

The slice is narrower than the accepted batch90 savepoint reader coverage: batch90 proved the current source and a pinned reader forcing `preserve_wal`; this next94 slice proves that releasing that reader lets a restart/truncate checkpoint move new readers to checkpointed database bytes while preserving exact current WAL source validation.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointReaderCheckpointCurrentSourceNext94Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 66 assertions, 0 failures
```

Lint:

```text
php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php
php -l lanes/libsqlite/tests/SQLiteWalSavepointReaderCheckpointCurrentSourceNext94Test.php
php -l lanes/libsqlite/examples/application-wal-savepoint-reader-checkpoint-current-source-next94.php
No syntax errors detected
```

Smoke:

```text
php lanes/libsqlite/examples/application-wal-savepoint-reader-checkpoint-current-source-next94.php --self-test
application-wal-savepoint-reader-checkpoint-current-source-next94 self-test passed
```

Whitespace:

```text
git diff --check -- lanes/libsqlite
```

passed with no output.

## Dashboard Delta

Expected focused PASS-line movement is `+66` for the new lane-scoped test file (`36393 -> 36459`) with `0` failures. Mapped coverage is unchanged because this is a current-source WAL closure behavior built on the existing WAL/savepoint mapped inventory.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP WAL parser, savepoint stack, durable checkpoint result, and sidecar-write dependency marker.

## Non-Overlap

Avoids accepted WAL reader-pin restart/truncate handoff, batch90 savepoint current-source pinned-reader coverage, WAL byte truncation, VFS savepoint rollback application, rollback-journal apply, and checkpoint transaction plan surfaces. The new behavior is the reader-release checkpoint transition after savepoint rollback.
