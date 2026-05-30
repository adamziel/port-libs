# Real Upstream Corpus UPSERT RETURNING Dynamic 20260530T184555Z-0

Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`.

This slice adds `SQLiteRealUpstreamUpsertReturningDynamicPriorityMatrixTest.php`
with 1,025 focused TestRunner PASS cases and 2,185 assertions from real SQLite
upstream behavior:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  `upsert5-1.1` through `upsert5-3.1`: multi-arm UPSERT conflict priority,
  arm-order selection, insert/update partitioning, and RETURNING projection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  `returning1-17.1` and `returning1-17.2`: multi-row UPSERT RETURNING rowid
  stream, duplicate update order, refcount updates, and projection behavior.

Non-overlap:

- This is a separate current-base priority matrix file. It does not edit or
  replace the accepted dynamic UPSERT files:
  `SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php`,
  `SQLiteRealUpstreamUpsertReturningDynamicArmsCorpusTest.php`,
  `SQLiteRealUpstreamUpsertReturningDynamicStatementTest.php`,
  `SQLiteRealUpstreamUpsertReturningDynamicPriorityTest.php`, or
  `SQLiteRealUpstreamUpsert5FullMatrixTest.php`.
- It adds PASS-line growth only; mapped denominator coverage is unchanged.
- No domain-specific API or source names were added.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicPriorityMatrixTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicPriorityMatrixTest.php`
  passed: `1 test files, 2185 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses existing bounded native PHP
  UPSERT/RETURNING row-array execution helpers.
