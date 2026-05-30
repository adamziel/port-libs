# WAL hot-journal checkpoint reader current-source next120

Implemented `SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan`, a
current-source guard for the WAL/pager path where a copied Application database
has both a hot rollback journal and a stale reader WAL source. The planner:

- recovers the hot rollback journal before interpreting WAL visibility;
- rolls back the failed savepoint tail to the retained WAL prefix;
- keeps a pinned reader on its stale source while preventing checkpoint reset;
- proves that, after reader release, restart/truncate checkpoint state uses
  the retained current WAL prefix rather than the stale reader WAL bytes.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalCheckpointReaderCurrentSourceNext120Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 74 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `46412` to `46486` from 74 focused PASS
lines. Mapped upstream coverage remains `604 / 1589`; this is focused PHP
behavior over already mapped WAL/hot-journal/savepoint/checkpoint inventory,
not a newly hydrated upstream denominator row.

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-checkpoint-reader-current-source-next120.php
```

Non-overlap: avoids accepted WAL savepoint reader checkpoint next117,
checkpoint hot-journal savepoint next114, WAL restart/truncate reader,
rollback-journal apply, VFS savepoint rollback apply, and checkpoint
transaction clusters. This slice adds the missing stale-reader-current-source
guard across hot rollback recovery plus checkpoint reset/release.

Dependency closure: no new support component is needed; this composes existing
native PHP rollback-journal recovery, WAL savepoint truncation, and checkpoint
reader-source primitives.

Next task: broader pager/VFS transaction application for WAL checkpoint/reset
state with file-handle persistence, or another distinct WAL durability edge.
