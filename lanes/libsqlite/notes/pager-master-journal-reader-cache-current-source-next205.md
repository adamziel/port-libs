# Pager Master-Journal Reader Cache Current Source Next205

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext205Plan`, a focused
pager reader-cache fence for attached rollback-journal master-name tickets.
After master-journal recovery, cache pages may only be reused when each member
rollback journal names the current master journal and the reader transaction's
master-name digest still matches the current page set. A page with identical
bytes but an old embedded master-journal name forces the whole reader
transaction to reopen before the next read.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext205Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext205Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next205.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext205Test.php`
  - `1 test files, 75 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next205.php`
  - `application-pager-master-journal-reader-cache-current-source-next205 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` increases by 75 focused PASS lines after
clean integration. Mapped upstream coverage remains unchanged; this is focused
pager behavior over existing master-journal reader-cache inventory rather than
a fresh manifest row.

## Non-Overlap

This slice avoids accepted next203 member order, next200 member generation,
next196 member header digest, next192 member token, next191 delete/sync proof,
and rollback/super-journal/VFS application paths. The new behavior is only the
rollback-journal embedded master-name fence before current-source reader-cache
reuse.

## Dependency Closure

No new support component is needed. The slice reuses lane-local
master-journal member parsing, pager reader-cache tickets, and bounded current
source page-image metadata.
