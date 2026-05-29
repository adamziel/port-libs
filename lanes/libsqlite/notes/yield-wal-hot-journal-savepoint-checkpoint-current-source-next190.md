# WAL hot-journal savepoint checkpoint current-source next190

## Slice

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
post-reader-fence publication guard for the WAL hot-journal/savepoint/checkpoint
current-source chain. It verifies that a retry checkpoint source can be
published only after the accepted next187 reader-token fence and after the final
file map still has:

- the expected database bytes,
- a parseable WAL with the expected page size/checkpoint sequence and at least
  one commit frame,
- no remaining hot rollback journal,
- directory-sync evidence when required.

## WordPress path

`examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next190.php`
models a copied `wp_options` database retry checkpoint after a hot-journal
recovery/savepoint cycle. The smoke emits the publication token, commit-frame
count, no-hot-journal state, and dependency-closure note.

## Evidence

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext190Test.php
```

Verified focused delta: 63 PASS lines in a lane-scoped test file.

## Non-overlap

This slice avoids accepted next183/184/187 source-token admission behavior,
WAL byte truncation, rollback-journal apply, checkpoint transaction planning,
reader-cache token fencing, and WAL header-source parsing. It is a final
publication/file-map guard after those behaviors have already succeeded.

## Dependency Closure

No new support component is needed. The plan reuses existing native WAL parsing
and checksum validation plus accepted reader-token fencing evidence.
