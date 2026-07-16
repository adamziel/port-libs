# Real upstream corpus VFS I/O dynamic journal1 stale rollback isolation

Session: `port-dev-sqlite-yield-dyn-real-vfs-20260531T074814Z`

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T074814Z-0`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal1.test`
- Ported sections:
  - `journal1-1.1`: create a database, force a rollback journal, copy the
    journal aside, and roll back to the original image.
  - `journal1-1.2`: delete the database, copy the old journal beside a newly
    created database of the same name, and confirm the stale journal is ignored
    instead of replayed into the new database.

## Patch

Added `SQLiteRealUpstreamCorpusVfsJournal1IsolationDynamicTest.php` with 2,000
dynamic real upstream cases plus 2 guard/source cases. The coverage exercises
the existing native `SQLiteVfsIoDynamicPlan::staleRollbackJournalIsolationProfile()`
surface over row counts, deleted-row counts, old/new database page counts,
copied-journal presence, and atomic-batch-write skip behavior.

## Non-overlap

This does not repeat accepted `journal1` new-database profile assertions,
rollback-journal apply/commit, super-journal commit, VFS writer/sync/lock,
`io.test` quick-balance/atomic/default-page-size/cache-spill matrices,
`ioerr*`, `tempfault`, `syscall`, `mmap`, `walvfs`, appendvfs, checksum VFS,
B-tree, JSON, SQL, or PRAGMA corpus work. The owned surface is the previously
untested stale rollback-journal hotness isolation profile for recreated
databases.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsJournal1IsolationDynamicTest.php`
  - `1 test files, 46011 assertions, 0 failures`
  - `2002` focused TestRunner PASS cases

## Dependency closure

No new support component is required. The slice reuses the existing bounded
native VFS I/O dynamic planner and the hydrated upstream `journal1.test`
source truth.
