# real-upstream-corpus-pager-wal-dynamic-20260531T010313Z-0

Accepted base: `db598d2f37de4eb8809eabdfe8470ae863639e6e`.

Added `SQLiteRealUpstreamPager2SavepointDynamicCorpusTest.php`, a real upstream
pager corpus slice sourced from hydrated SQLite
`/home/claude/port-libs/.upstream-cache/libsqlite/test/pager2.test`.

Upstream scenarios covered:

- `pager2.test` `pager2-1.1`: default rollback-journal savepoint churn,
  page size 512.
- `pager2.test` `pager2-1.2`: `journal_mode=memory`, page size 1024.
- `pager2.test` `pager2-1.3`: `journal_mode=memory` with exclusive locking,
  page size 1024.
- `pager2.test` `pager2-1.4`: safe-append sector behavior, page size 2048.
- `pager2.test` `pager2-1.5`: default rollback-journal savepoint churn,
  page size 4096.
- `pager2.test` `pager2-1.6`: WAL-mode savepoint churn, page size 4096.
- `pager2.test` `pager2-1.7`: auto-vacuum savepoint churn, page size 4096.
- `pager2.test` `pager2-1.8`: synchronous-off savepoint churn, page size 8192.

Focused movement:

- `+2001` distinct TestRunner PASS cases.
- `22825` focused behavior assertions.
- Expected selected `phpPass`: `1408188 -> 1410189` if accepted.
- Mapped denominator remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPager2SavepointDynamicCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPager2SavepointDynamicCorpusTest.php`
  passed with `1 test files, 22825 assertions, 0 failures`.

Non-overlap:

This slice does not touch accepted WAL checkpoint, WAL byte truncation,
rollback-journal commit/apply, pager WAL dynamic restart/overwrite/checksum,
or app-WAL surfaces. It ports the upstream `pager2.test` long savepoint stress
sequence and validates native `SQLiteSavepointStack` page-image rollback
behavior across the upstream pager configurations.

Dependency closure:

No new support component is needed. The slice reuses the existing native
`SQLiteSavepointStack` bounded pager/savepoint component and local PHP
TestRunner only.
