# WAL hot-journal savepoint checkpoint current-source next166

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`,
which layers RELEASE lineage validation onto the existing hot-journal,
savepoint, WAL checkpoint, and reader-cache current-source fence.

Behavior covered:

- hot-journal pages are restored before checkpoint visibility is computed;
- inner savepoint rollback pages must be present in the RELEASE-to-outer
  lineage before checkpoint current-source publication;
- checkpoint and next-WAL source tokens are fenced after release validation;
- retained and invalidated reader-cache pages are reported with write-barrier
  order for WordPress import retries.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext166Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 62 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next166.php
status: wal-hot-journal-savepoint-checkpoint-current-source-release-next166
release_complete: true
```

Non-overlap:

This does not repeat accepted WAL byte truncation, rollback-journal apply,
checkpoint transaction planning, VFS savepoint rollback apply, or next161
reader-cache token fencing. The new behavior is the RELEASE-to-outer-savepoint
lineage gate before checkpoint current-source publication.

Dependency closure:

No new support component is needed. The plan reuses native WAL parsing,
durable checkpoint planning, reader snapshot image lookup, and the existing
next161 hot-journal/savepoint/checkpoint cache-fence primitive.
