# pager-master-journal-reader-cache-current-source-next193

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext193Plan`, a stable repeated-read fence for pager reader-cache reuse after master-journal recovery. The current master journal must be read at least twice with identical bytes/digests before clean reader-cache entries can cross into the next current source. Cache entries and next-read tickets now carry `stable_master_read_token`; mismatches force a reader reopen even when recovered page-set, publication, format, and member rollback-journal digest tickets still match.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext193Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheStableMasterReadTokenFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-stable-master-read-token-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheStableMasterReadTokenFenceTest.php`
  - `1 test files, 51 assertions, 0 failures`
  - 51 focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-stable-master-read-token-fence.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-stable-master-read-token-fence self-test passed`

## Non-overlap

This slice does not repeat next189 per-member rollback-journal digest fencing, next187 complete-read membership ordinals, next184 file stat/read-token fencing, next183 publication generation, next180 format-ticket fencing, VFS rollback/commit/sync/write clusters, WAL checkpoint/savepoint byte truncation, or B-tree/SQL/JSON/encoding surfaces. It specifically covers the torn/replaced master-journal read edge where the member list and member journal bytes may otherwise look current.

## Dependency closure

No new support component is needed. The patch reuses lane-local pager master-journal reader-cache current-source primitives and adds only a bounded native PHP stable-read token admission check.
