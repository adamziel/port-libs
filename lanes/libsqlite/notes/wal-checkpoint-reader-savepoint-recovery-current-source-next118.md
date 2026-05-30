# WAL Checkpoint Reader Savepoint Recovery Current Source Next118

Status: focused PHP behavior growth for WAL checkpoint reader savepoint recovery current-source admission.

## Behavior

- Added `SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNextPlan`.
- Models a reader-pinned WAL checkpoint after `ROLLBACK TO` trims a savepoint prefix, then crash recovery from the retained WAL source.
- Verifies that recovery replays only the retained savepoint prefix and never replays discarded savepoint frames.
- Rejects stale current WAL bytes and stale persisted WAL sidecar bytes that do not match the retained prefix.
- Covers restart, sidecar-written restart, truncate, retained-reader, and copied Application `wp_options` import recovery paths.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNext118Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 69 assertions, 0 failures
```

Focused test delta: +69 PASS lines.

## Application Smoke

`lanes/libsqlite/examples/application-wal-checkpoint-reader-savepoint-recovery-current-source-next118.php --self-test` verifies copied `wp_options` WAL recovery after a plugin settings savepoint rollback and reader-pinned checkpoint crash.

## Non-Overlap

Avoids accepted WAL checkpoint reader savepoint current-source next104, WAL savepoint release/checkpoint next79/84/91, WAL byte truncation, VFS savepoint rollback apply, WAL checkpoint transactions, rollback-journal/super-journal commit/apply, durable checkpoint file-write/sync, readmark/salt/checksum recovery, and B-tree/JSON/SQL/encoding clusters. This slice only adds the recovery admission step after the retained savepoint WAL prefix is selected.

## Dependency Closure

No new support component is needed. The slice reuses native PHP WAL parsing/checksum validation, savepoint WAL prefix truncation, reader checkpoint visibility, and checkpoint crash recovery primitives.

## Next

Continue with broader pager/VFS transaction application or a distinct WAL recovery edge; avoid another reader-savepoint wrapper unless it applies a new durability or source-admission rule.
