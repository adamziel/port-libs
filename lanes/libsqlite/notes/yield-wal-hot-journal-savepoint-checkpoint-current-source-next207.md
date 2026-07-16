# WAL Hot-Journal Savepoint Checkpoint Current Source Next207

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
write-cursor admission fence layered after the accepted next206 reopened
statement generation check. A Application import write cursor may commit after
hot-journal recovery, savepoint rollback, WAL checkpoint publication, and
statement reprepare only when it:

- belongs to a consumer admitted by next206;
- observes the current checkpointed database digest, WAL digest, and root page
  digests;
- has a commit generation at or after the checkpoint publication;
- holds the expected exclusive WAL write-lock token;
- is not read-only, dirty, still inside a savepoint, or carrying stale
  hot-journal identity.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext207Test.php
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next207.php
```

## Dashboard Delta

Expected libsqlite `phpPass`: `100087 -> 100159` (`+72`) on acceptance, from
the focused PASS-line count in this slice. Mapped upstream coverage remains
`621 / 1589`; this slice does not claim a new manifest-backed upstream row.

## Non-Overlap

This slice validates write-cursor commit admission after next206 statement
reprepare. It does not repeat WAL byte truncation, rollback-journal
apply/commit, VFS savepoint rollback, checkpoint transaction planning, WAL
sidecar lease checks, or next206 prepared-statement generation quarantine.

## Dependency Closure

No new support component is needed. The slice reuses next206 checkpoint
generation, page digests, and existing WAL/VFS lock evidence to fence write
cursor admission.
