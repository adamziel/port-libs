# WAL Hot-Journal Savepoint Checkpoint Current Source Next203

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
post-sidecar page-cache lease fence for the WAL/hot-journal/savepoint/checkpoint
path. After the accepted next196 WAL sidecar publication gate, a cached
statement or reader lease is retained only when:

- its observed WAL sidecar digest matches the published sidecar;
- its observed root page digests match the checkpointed database image;
- the lease is not closed or dirty;
- the checkpointed database image is complete by page size.

Stale WAL digests, stale page digests, closed/dirty leases, and pages outside
the checkpointed image are routed to reopen.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext203Test.php
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next203.php
```

## Dashboard Delta

Expected libsqlite `phpPass`: `97068 -> 97124` (`+56`) on acceptance, from the
focused PASS-line count in this slice. Mapped upstream coverage remains
`619 / 1589`; this slice does not claim a new manifest-backed upstream row.

## Non-Overlap

This slice validates checkpointed database page-cache lease reuse after next196
WAL sidecar publication. It does not repeat next196 restart/truncate/preserve
sidecar decisions, next192 checkpoint page-image publication, reader retry
admission, VFS savepoint rollback, rollback-journal apply, or WAL byte
truncation planning.

## Dependency Closure

No new support component is needed. The slice reuses lane-local WAL sidecar
publication metadata and checkpointed database page digests.
