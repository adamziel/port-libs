# real-upstream-corpus-vfs-io-dynamic-exclusive-locking-20260531T121117Z

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T121117Z-0`

Base accepted HEAD: `e4074c45f1e9d3c2408ad3ef65aec8f4e6ec75cf`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/exclusive.test`
  - `exclusive-1.0` through `exclusive-1.13`: `PRAGMA locking_mode`
    propagation for main/temp/attached databases.
  - `exclusive-2.0` through `exclusive-2.11`: exclusive connections retain
    shared/exclusive locks until a normal-mode access releases them.
  - `exclusive-3.0` through `exclusive-3.6`: exclusive commits truncate the
    rollback journal instead of deleting it, then normal access deletes it.
  - `exclusive-4.0` through `exclusive-4.5`: rollback in exclusive mode
    preserves the cached table signature.
  - `exclusive-5.0` through `exclusive-5.7`: exclusive mode keeps rollback and
    statement-journal handles open after commit.
  - `exclusive-6.2` through `exclusive-6.5`: exclusive mode opens copied
    hot-journal and stray-journal databases.
  - `exclusive-7.1`: WAL toggle through exclusive/normal mode preserves pager
    change-count state.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/exclusive2.test`
  - `exclusive2-1.0` through `exclusive2-1.11`: normal mode uses the
    change-counter to decide when to discard pager cache.
  - `exclusive2-2.1` through `exclusive2-2.8`: exclusive mode hides on-disk
    corruption behind pager cache until normal unlock.
  - `exclusive2-3.0` through `exclusive2-3.6`: exclusive mode increments the
    database change-counter only once until lock release finishes.

## Behavior Added

- Added `SQLiteVfsIoDynamicPlan::exclusiveLockingProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsExclusiveLockingDynamicTest.php` with 1200
  dynamic profile cases plus upstream-citation and malformed-input checks.
- The dynamic matrix varies page size, cache pages, row counts, pragma
  assignment, attached database count, lock-retention phases, journal lifecycle
  phases, statement-journal handle phases, hot-journal cases, and
  change-counter sequences.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsExclusiveLockingDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsExclusiveLockingDynamicTest.php`
  - `1 test files, 24986 assertions, 0 failures`
  - `1203` focused PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsIoDynamicUpstreamCorpusTest.php`
  - `1 test files, 934 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

## Non-Overlap

This slice does not repeat accepted `io.test` atomic-write exclusive cases,
WAL exclusive/no-SHM protocol slices, VFS lock-state, process-lock, superlock,
locked writer, rollback-journal apply/commit, sync plan/apply, short 8.3
sidecars, delete-db sidecars, or syscall/mmap/quota clusters. It focuses on
the separate upstream `exclusive.test` and `exclusive2.test` pager/VFS locking
and cache-coherency behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing generic
`SQLiteVfsIoDynamicPlan` VFS/pager profile surface and adds one bounded native
PHP behavior profile.
