# Pager Hot Journal Cache Spill Current Source Next127

## Behavior

Adds `SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan`, which models the pager ordering needed after an interrupted rollback-journal transaction: recover hot-journal before-images first, then make cache-spill decisions against that recovered current source. Dirty cache pages whose source image still matches the stale database image are blocked instead of being spilled over the recovered pages.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalCacheSpillCurrentSourceNext127Test.php`
- Result: `1 test files, 68 assertions, 0 failures`
- PHP lint passed for the changed source, test, and example files.
- Application smoke: `lanes/libsqlite/examples/application-pager-hot-journal-cache-spill-current-source-next127.php`

## Non-Overlap

This is distinct from accepted next120 savepoint-only cache-spill recovery, next123 statement/master current-source recovery, rollback-journal commit/apply, super-journal commit, VFS writer/sync/lock behavior, WAL checkpoint transaction, and WAL byte-truncation slices. The new surface is the hot-journal recovery ordering before current-source cache-spill eligibility.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SQLitePagerDirtyPageCacheSpillPlan` and adds the bounded hot-journal current-source source map around it.
