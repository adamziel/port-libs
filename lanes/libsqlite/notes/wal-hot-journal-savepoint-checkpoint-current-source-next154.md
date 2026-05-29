# WAL Hot-Journal Savepoint Checkpoint Current Source Next154

- Added `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` for the current-source WAL path where hot rollback-journal recovery happens before a savepoint rollback and checkpoint reset.
- The new behavior compares checkpoint database bytes against the savepoint-visible WAL frame boundary, not the full current WAL tail, so failed savepoint tail frames are explicitly detected and discarded before reset.
- Focused test evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext154Test.php` passes with 1 file / 76 assertions / 0 failures.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next154.php` passes and models a copied plugin import recovering a hot journal before checkpointing the savepoint-visible pages.
- Dependency closure: no new support component needed; this reuses native rollback-journal parsing/recovery, WAL parsing/snapshot helpers, and checkpoint source comparison.
- Non-overlap: avoids accepted next148 end-of-WAL checkpoint matching, WAL byte truncation, WAL checkpoint transaction, rollback-journal apply, VFS writer/savepoint rollback, and hot-journal reader checkpoint slices by gating the checkpoint database on the savepoint frame boundary.
