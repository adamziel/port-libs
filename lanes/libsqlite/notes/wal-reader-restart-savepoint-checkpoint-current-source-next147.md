# WAL Reader Restart Savepoint Checkpoint Current Source Next147

- Behavior: after `ROLLBACK TO` truncates WAL frames for a failed Application import savepoint, a restart checkpoint with an active reader preserves that reader on the truncated current WAL source while later writers append retry frames to the restarted WAL header generation.
- Non-overlap: avoids accepted WAL byte-truncation-only, WAL checkpoint transaction, hot-journal reader restart, VFS savepoint rollback, and truncate-reader current-source slices by composing savepoint rollback, restart checkpoint current-reader pinning, and next-generation append visibility in one bounded plan.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderRestartSavepointCheckpointCurrentSourceNext147Test.php`.
- Application smoke: `php lanes/libsqlite/examples/application-wal-reader-restart-savepoint-checkpoint-current-source-next147.php`.
- Dependency closure: no new support component needed; this reuses native WAL parsing/checksums, `SQLiteSavepointStack` WAL byte truncation, restart checkpoint planning, reader snapshots, and WAL append frame checksums.
