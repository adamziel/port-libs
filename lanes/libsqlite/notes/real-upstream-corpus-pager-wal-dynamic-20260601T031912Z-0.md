## Real Upstream Pager/WAL Dynamic: pager1 rollback max_page_count

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Upstream sections: `pager1-44.1` through `pager1-44.3`

Ported behavior:

- `pager1-44.1` seeds a full auto-vacuum database with 31 pages.
- `pager1-44.2` drops a table, runs incremental vacuum, then clamps
  `PRAGMA max_page_count=2` to the current 16-page image.
- `pager1-44.3` rolls back and requires both `page_count` and
  `max_page_count` to be restored upward to 31.

Patch evidence:

- Added `SQLiteRealPagerBoundaryPlan::rollbackRestoresMaxPageCount()`.
- Added `SQLiteRealUpstreamCorpusPagerWalPager1RollbackMaxPageDynamic20260601Test.php`.
- Focused PASS growth: `+1003` real TestRunner cases.
- Focused assertion count: `24021`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1RollbackMaxPageDynamic20260601Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1RollbackMaxPageDynamic20260601Test.php`
  - `1 test files, 24021 assertions, 0 failures`

Non-overlap:

- This targets `pager1-44` rollback-time `max_page_count` restoration.
- It does not repeat accepted `pager1-6` clamp-only rows, page-size rewrite,
  journal-mode boundaries, VFS writer/sync/lock, rollback-journal apply/commit,
  WAL byte truncation, `savepoint2` WAL signature preservation, or `pager4`
  DBMOVED coverage.

Dependency closure:

- No new support component is needed.
- The slice reuses source-neutral pager boundary modeling and the hydrated
  upstream `pager1.test` source.

Root harness:

- Not run - isolated micro-slice.
