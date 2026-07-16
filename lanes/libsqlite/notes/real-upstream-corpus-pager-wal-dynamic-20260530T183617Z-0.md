# real-upstream-corpus-pager-wal-dynamic-20260530T183617Z-0

Base accepted HEAD: `365df791b359e0dd925a461a6d36ddf8a8d0f5f1`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`
- Ported scenarios:
  - `walckptnoop-1.1`: `PRAGMA wal_checkpoint = noop` reports WAL log frames and checkpoints zero frames.
  - `walckptnoop-1.2`: repeated `NOOP` checkpoint remains side-effect free.
  - `walckptnoop-1.3`: `PASSIVE` checkpoint backfills committed WAL pages into the database image.
  - `walckptnoop-1.4`: post-`PASSIVE` `NOOP` does not backfill additional bytes and preserves the checkpointed database image.

## Behavior

Adds a focused dynamic corpus test over the existing native `SQLiteWal`
checkpoint implementation. The test constructs 625 distinct valid WAL byte
images with varied frame counts, page counts, and overwritten page images, then
checks that `NOOP` never mutates database bytes while `PASSIVE` applies the
latest committed image for each page. The test also keeps the unsupported-mode
guard covered.

No production source changes were needed, and no domain-specific API names were
added.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWalNoopCheckpointDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWalNoopCheckpointDynamicCorpusTest.php`
  - `1 test files, 15091 assertions, 0 failures`
  - `626` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This slice does not repeat the accepted WAL byte-truncation, WAL checkpoint
transaction, rollback-journal apply/commit, VFS file-writer, locked writer, or
VFS sync-plan/apply clusters. It specifically covers upstream
`walckptnoop.test` NOOP checkpoint semantics against varied WAL byte images.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
`SQLiteWal` parser, checksum, checkpoint, and durable checkpoint result
implementation.
