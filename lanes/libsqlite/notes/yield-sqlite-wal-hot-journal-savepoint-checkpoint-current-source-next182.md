# WAL hot-journal/savepoint checkpoint current source next182

Slice: `wal-hot-journal-savepoint-checkpoint-current-source-next182`

Behavior added:

- Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`.
- Composes the accepted next167 current-source publication guard, then decides
  which prepared statements can remain cached after hot rollback-journal
  recovery, rollback-to-savepoint, and WAL checkpoint publication.
- Statements are admitted only when source token, epoch, schema cookie, and
  root-page exposure still match the checkpoint current source. Statements
  touching hot-journal or savepoint-rollback root pages are forced to reprepare
  when the schema cookie advances.
- Includes a WordPress smoke for copied plugin import / option reads.

Non-overlap:

- Does not repeat next167 publication fingerprints, next164 reader admission,
  VFS byte application, pinned-reader preservation, WAL byte truncation, or
  accepted hot-journal/savepoint checkpoint file writes.
- This slice is statement-cache admission after the already-published WAL
  current source.

Verification:

- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext182Test.php`
- Example smoke: `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next182.php`
- PHP lint: changed PHP files under this slice.
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component needed; this reuses native WAL parsing, current
  source publication guards, hot-journal/savepoint checkpoint planning, and
  statement-cache metadata.
