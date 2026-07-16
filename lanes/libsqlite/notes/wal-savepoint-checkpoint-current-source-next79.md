# WAL Savepoint Checkpoint Current Source Next79

Status: focused PHP behavior growth for WAL savepoint rollback/checkpoint current-source guarding.

## Behavior

- `SQLiteWalSavepointCheckpointPlan::afterRollbackTo()` now validates that the parsed `SQLiteWal` object and raw WAL bytes are the same current source before truncating to a savepoint prefix and checkpointing.
- The guard rejects stale WAL bytes with a different salt, stale checkpoint sequence, or different frame count, preventing a savepoint rollback boundary from being applied to the wrong WAL source.
- Safe matching WAL bytes still produce the retained prefix, checkpoint database image, RESTART/TRUNCATE reset metadata, and reader current/next visibility.

## Verification

```bash
php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php
php -l lanes/libsqlite/tests/SQLiteWalSavepointCheckpointCurrentSourceNext79Test.php
php -l lanes/libsqlite/examples/application-wal-savepoint-checkpoint-current-source-next79.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointCheckpointCurrentSourceNext79Test.php
php lanes/libsqlite/examples/application-wal-savepoint-checkpoint-current-source-next79.php
git diff --check -- lanes/libsqlite
```

Focused test delta: +52 PASS lines in `SQLiteWalSavepointCheckpointCurrentSourceNext79Test.php`.

## Non-Overlap

Avoids accepted WAL savepoint byte truncation, VFS savepoint rollback application, WAL checkpoint transaction planning, WAL savepoint restart-checkpoint application current-next74, WAL reader-pin/restart/checksum/salt recovery slices, rollback-journal/super-journal paths, and VFS file-writer/sync/lock clusters. This slice only adds current-source admission before the existing savepoint rollback/checkpoint path can use raw WAL bytes.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP WAL parsing/checksum validation, savepoint WAL frame accounting, and durable checkpoint planning.

## Next

Continue with broader pager/VFS transaction application or a distinct WAL recovery edge; avoid another savepoint checkpoint wrapper unless it applies a new durability rule.
