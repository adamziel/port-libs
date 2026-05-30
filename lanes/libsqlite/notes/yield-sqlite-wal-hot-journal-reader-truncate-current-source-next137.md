# WAL Hot-Journal Reader Truncate Current-Source Next137

## Behavior

Adds `SQLiteWalHotJournalReaderTruncateCurrentSourceNextPlan`, a bounded current-source WAL behavior that composes existing native primitives:

- recover a hot rollback journal before using the database image for WAL checkpoint work;
- verify the current reader WAL source matches the current WAL bytes;
- feed the recovered hot-journal database bytes into truncate checkpoint planning;
- preserve the current reader snapshot while truncate is blocked by the reader;
- remove the old WAL sidecar after reader release and append the next writer on a fresh WAL generation.

This is intentionally disjoint from accepted next132 reader/source validation, next134 truncate-reader checkpoint behavior, and next135 next-generation reader continuity because next137 verifies the recovered hot-journal database image is the source passed into truncate checkpoint planning.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalReaderTruncateCurrentSourceNext137Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 57 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-reader-truncate-current-source-next137.php
```

## Dependency Closure

No new support component is needed. The slice reuses native rollback-journal recovery, WAL parsing, reader current-source validation, truncate checkpoint planning, and WAL append planning.

## Non-Overlap

Avoids accepted WAL checkpoint truncate-reader next134, WAL checkpoint reader hot-journal next132, WAL hot-journal checkpoint reader next135, WAL byte truncation, rollback-journal apply, VFS file writer, and checkpoint transaction clusters. The new behavior is the integration boundary between hot-journal recovery and truncate checkpoint source selection.
