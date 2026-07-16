# WAL Reader Checkpoint Savepoint Current Source Next139

Slice: `wal-reader-checkpoint-savepoint-current-source-next139`

This slice adds `SQLiteWalSavepointCheckpointPlan::readerCheckpointSavepointCurrentSourceNext()`.

Behavior covered:

- verifies the raw WAL bytes and SHM salt/mxFrame still match the parsed current WAL source;
- keeps an already-active reader pinned to the original WAL commit frame even when that frame is inside the savepoint frames a writer rolls back;
- uses the retained WAL prefix as the writer/checkpoint current source after `ROLLBACK TO`;
- shows an active read-mark blocks `RESTART`/`TRUNCATE` reset while released SHM read-marks allow the next reader to use the checkpointed database image;
- rejects stale WAL bytes, SHM salt mismatch, SHM mxFrame mismatch, missing reader pins, empty page lists, and unsupported checkpoint modes.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php
php -l lanes/libsqlite/tests/SQLiteWalReaderCheckpointSavepointCurrentSourceNext139Test.php
php -l lanes/libsqlite/examples/application-wal-reader-checkpoint-savepoint-current-source-next139.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointSavepointCurrentSourceNext139Test.php
php lanes/libsqlite/examples/application-wal-reader-checkpoint-savepoint-current-source-next139.php
git diff --check -- lanes/libsqlite
```

Focused result:

`1 test files, 61 assertions, 0 failures`

Expected dashboard movement: `phpPass +61` from `60223` to `60284`. Mapped upstream coverage is unchanged at `606 / 1589`.

Non-overlap: this avoids accepted WAL hot-journal reader truncate, WAL restart/truncate savepoint reader next105, recovery checkpoint savepoint next100, savepoint release checkpoint next84, WAL byte truncation, VFS savepoint rollback, VFS rollback-journal/commit/sync/write/lock clusters, WAL checkpoint transaction, B-tree/JSON/SQL/encoding accepted surfaces, and queued VFS/schema conflict surfaces. The new behavior is the split between an active reader pinned to the original current WAL and the writer's retained-prefix checkpoint source after savepoint rollback.

Dependency closure: no new support component is needed. The slice reuses existing bounded native PHP primitives: `SQLiteWal`, `SQLiteShmIndex`, and `SQLiteSavepointStack`.

Next task: continue with broader pager/VFS transaction application or another non-overlapping WAL durability edge; avoid another savepoint checkpoint wrapper unless it applies bytes through a distinct pager/file-handle path.
