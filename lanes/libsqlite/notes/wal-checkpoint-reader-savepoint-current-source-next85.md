# WAL Checkpoint Reader Savepoint Current Source Next85

## Behavior

Adds `SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext()` for the WAL/savepoint current-source gap. The wrapper builds on the existing rollback-to-savepoint and checkpoint reader boundary, then emits per-page before/current/next source rows:

- retained pages stay `wal>wal>wal`;
- pages discarded by `ROLLBACK TO` move `wal>database>database`;
- readers already pinned at the retained prefix report `database>database>database` for discarded pages;
- restart/truncate checkpoints blocked by the retained reader preserve WAL source state instead of claiming database-only visibility.

This is intentionally separate from accepted byte truncation, savepoint rollback apply, WAL checkpoint transaction planning, VFS writer, and rollback-journal commit work. It only reports reader-visible current/next source provenance after the savepoint checkpoint boundary.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderSavepointCurrentSourceNext85Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
64 PASS lines
1 test files, 64 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-wal-checkpoint-reader-savepoint-current-source-next85.php
```

Result: JSON scenario reports `status=busy`, `checkpointReason=reader_blocks_wal_reset`, retained frames `[1,2]`, discarded pages `[3,4,5]`, and source transitions `wal>wal>wal` for retained pages plus `wal>database>database` for rolled-back plugin-setting pages.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded WAL parser, savepoint stack, reader snapshot, and durable checkpoint helpers.

## Next

A follow-up WAL slice should apply these source rows through broader pager/VFS transaction state only if it adds real write/durability behavior; do not add another reader-source metadata variant.
