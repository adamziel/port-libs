# pager-master-journal-reader-cache-current-source-next189

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext189Plan`, a current-source fence for master-journal recovery when the master-journal member list is unchanged but one member rollback-journal file has been replaced or reread with different bytes. Reader-cache entries and next-read tickets now carry the member rollback-journal path and digest; mismatches force reopen instead of reusing a stale page image.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext189Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext189Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next189.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext189Test.php`
  - `1 test files, 61 assertions, 0 failures`
  - 61 focused PASS lines
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next189.php`
  - `application-pager-master-journal-reader-cache-current-source-next189 self-test passed`

## Non-overlap

This slice does not repeat next186 recovered page-set sequence fencing, next183 publication-generation/master-source digest fencing, next180 format-ticket checks, finite rollback truncation, accepted super-journal commit behavior, or accepted rollback-journal commit/apply behavior. It is specifically the per-member rollback-journal digest fence behind a current master-journal source.

## Dependency closure

No new support component is needed. The slice reuses lane-local master-journal reader-cache current-source primitives and adds only the member rollback-journal digest ticket required to decide cache reuse.
