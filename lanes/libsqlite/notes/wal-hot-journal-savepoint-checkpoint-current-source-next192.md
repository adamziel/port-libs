# WAL Hot-Journal Savepoint Checkpoint Current Source Next192

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded
post-checkpoint admission guard for WAL/hot-journal/savepoint publication. The
plan verifies that checkpointed database pages contain the committed current WAL
frame images before prepared statements and reader caches are allowed to reuse
the current source.

This closes a durability gap after the accepted current-source token,
WAL-generation, and commit-hook guards: a cache can now be rejected when the
database image did not actually materialize a committed checkpoint page, or when
the cache observed an older page digest.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext192Test.php`
  - `1 test files, 64 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next192.php`
  - self-test JSON reports status `wal-hot-journal-savepoint-checkpoint-current-source-next192`

## Non-Overlap

This slice does not repeat accepted next188 commit-hook/schema-cookie admission,
next185 salt/checkpoint generation checks, next183 reader-token file-map
admission, VFS savepoint rollback application, rollback-journal apply/commit,
checkpoint transaction planning, WAL byte truncation, or WAL/header
retention-only validation. It specifically checks checkpointed database page
images against committed WAL frames before current-source cache reuse.

## Dependency Closure

No new support component is needed. The implementation reuses the native WAL
parser/checksum validator, reader snapshot page-image lookup, and existing
current-source admission chain.
