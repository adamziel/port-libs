# WAL hot-journal savepoint checkpoint current-source next209

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
post-checkpoint writer-generation fence layered after the accepted next206
reopened-statement consumer admission.

The new planner admits writer handles only when they:

- follow the published statement generation;
- observe the current checkpointed database, WAL, and consumer-generation
  digests;
- retain all current statement consumers;
- reopen every quarantined stale statement consumer;
- have no retained hot-journal identity;
- have no open savepoint scope, dirty page cache, or closed handle state.

It reports explicit reopen reasons for generation drift, old statement
generations, database/WAL/consumer digest mismatch, missing retained current
consumers, missing reopened stale consumers, retained hot-journal identity,
open savepoints, dirty cache, and closed writer handles.

## WordPress Smoke

`examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next209.php`
models a copied WordPress `wp_options` import after hot-journal recovery and
checkpoint publication. It admits the current autoload update writer and
reopens the stale plugin writer before any new WAL append.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext209Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 66 assertions, 0 failures
```

## Non-Overlap

This slice gates post-checkpoint writer handle reuse after next206
statement-consumer admission. It does not repeat WAL byte truncation, VFS
savepoint rollback, rollback-journal commit/apply, checkpoint transaction
planning, WAL file writing, or next206 reopened-statement consumer
classification.

## Dependency Closure

No new support component is needed. The slice reuses next206 statement
consumer fences, WAL/database digests, and lane-local writer handle metadata.
