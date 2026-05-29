# WAL Hot Journal Savepoint Checkpoint Current Source Next152

- Slice: `wal-hot-journal-savepoint-checkpoint-current-source-next152`
- Behavior: recover a hot rollback journal first, use that recovered database image as the current source, roll back a WAL savepoint to its retained frame prefix, then checkpoint that retained current WAL prefix.
- New focused test: `lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext152Test.php`
- WordPress smoke: `lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next152.php`

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext152Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 76 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next152.php
wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next152 self-test passed
```

Status delta:

- `phpPass`: `67368 -> 67444` (`+76` newly verified focused PASS lines)
- `phpFail`: remains `0`
- Mapped upstream coverage remains `606 / 1589`; this slice adds current-source behavior coverage without claiming a new manifest unit.

Dependency closure:

- No new support component needed.
- Reuses native rollback-journal hot recovery, `SQLiteSavepointStack` WAL byte truncation, `SQLiteWal` checkpoint durability, and existing focused runner tooling.

Non-overlap:

- Avoids accepted next145/next146 reader restart/truncate work by requiring hot rollback-journal recovery to establish the current database source before savepoint WAL rollback and checkpoint.
- Avoids accepted rollback-journal commit/apply, VFS savepoint rollback apply, WAL byte truncation-only, and WAL checkpoint transaction slices by composing their primitives into a narrower hot-journal plus savepoint current-source checkpoint boundary.
