# real-upstream-corpus-date-affinity-dynamic-20260530T222005Z

Base accepted HEAD: `2b1cf655ef1be10ae886e50a15d966f7036573f3`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- Ported sections:
  - `date2-300`: recursive population of 1000 rows as `julianday('2017-07-01')+x`
  - `date2-310`: reject non-deterministic `datetime('now')` in an expression index
  - `date2-320`: allow deterministic partial expression index over real date rows
  - `date2-331`: `datetime(b) BETWEEN '2017-07-04' AND '2017-07-08'` selects rows `3 4 5 6`
  - `date2-410`, `date2-430`, `date2-510`, `date2-520`: reject non-deterministic date/time modifiers in index predicates

## Evidence

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamDate2AffinityDynamicBatchTest.php`
- Focused PASS-line growth: `1009` new TestRunner PASS cases
- Focused behavior assertions: `5026`
- Expected selected throughput after acceptance: `929877 -> 930886`
- Mapped denominator movement: none; mapped coverage remains `1589 / 1589`

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDate2AffinityDynamicBatchTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate2AffinityDynamicBatchTest.php`
  - `1 test files, 5026 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP date/time scalar and date-schema guard helpers against real upstream `date2.test` behavior.
