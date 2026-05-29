# Pager master-journal savepoint cache current-source next125

## Behavior

Adds `SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan` for the
pager edge where a cached master-journal recovery has to be refreshed from the
current VFS source before savepoint retry pages are installed into the pager
cache. The plan rejects stale/dirty cache entries, installs recovered
master-journal savepoint before-images with a new source token, and reports
release reads that prove the next cache view uses recovered current-source
bytes.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalSavepointCacheCurrentSourceNext125Test.php`
  - `1 test files, 62 assertions, 0 failures`
  - `62` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-savepoint-cache-current-source-next125.php`
  - `wordpress-pager-master-journal-savepoint-cache-current-source-next125 self-test passed`
- PHP lint and `git diff --check -- lanes/libsqlite` were run for the changed lane files.

## Non-Overlap

This is not another master-journal savepoint recovery (`next108`), cache-spill
slice (`next114`), or stale master-journal membership refresh (`next122`). The
new behavior is the pager-cache current-source boundary after those steps:
stale dirty cache pages are invalidated, recovered savepoint before-images are
installed under a fresh source token, and post-release reads hit the refreshed
cache.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
rollback-journal parsing, master-journal cache recovery, savepoint rollback
preview, and pager cache source-token primitives.
