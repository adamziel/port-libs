# SQLite pager savepoint WAL cache recovery current-source next133

## Scope

This slice adds `SQLitePagerSavepointWalCacheRecoveryCurrentSourceNextPlan`, a bounded native PHP pager/WAL recovery planner for cache entries after `ROLLBACK TO` in WAL mode.

The behavior modeled here is current-source pager cache validation: cache pages sourced from WAL frames discarded by savepoint rollback are refreshed from the retained WAL prefix or base database image before later reads/checkpoint-style consumers use them. Retained cache pages remain valid when their WAL frame survives the rollback.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointWalCacheRecoveryCurrentSourceNext133Test.php`
  - `1 test files, 81 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-savepoint-wal-cache-recovery-current-source-next133.php`
  - emits `status: pager-savepoint-wal-cache-recovery-current-source-next133`

## Dashboard delta

- `phpPass`: `55029 -> 55110` (`+81` verified focused PASS lines).
- Mapped upstream coverage remains `606 / 1589`; this is a behavior-backed PHP pager/WAL cache recovery slice under existing WAL/savepoint inventory rather than a new manifest-mapped upstream row.

## Non-overlap

This avoids accepted WAL byte truncation, VFS savepoint rollback application, WAL checkpoint reader truncate reopening, master-journal WAL cache refresh, rollback-journal commit/apply, VFS writer/sync/lock clusters, and cache-spill savepoint recovery. It covers the narrower unhandled pager-cache source validation edge after savepoint WAL rollback.

## Dependency closure

No new support component is needed. The patch reuses `SQLiteSavepointStack::walRollbackToPlan()` and native PHP page-image/cache-source modeling; later pager/VFS transaction application can consume this planner without adding an external dependency.
