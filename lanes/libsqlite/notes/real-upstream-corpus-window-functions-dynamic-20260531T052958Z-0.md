# Real upstream corpus window functions dynamic 20260531T052958Z-0

- Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`.
- Added focused test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow1DynamicLateCorpusTest.php`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`.
- Ported source ranges:
  - `window1.test` `8.1.1-8.2.2`: persisted view window aggregate shape.
  - `window1.test` `9.1.1-9.3`: trigger/CTE max window behavior.
  - `window1.test` `10.1-10.8`: regional row_number top-N, cumulative partition sums, following frames, LIMIT/OFFSET, and correlated FILTER totals.
  - `window1.test` `12.100-13.4`: lead() with ORDER BY/LIMIT and compound rank arms.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1DynamicLateCorpusTest.php` passed with `1 test files, 3201 assertions, 0 failures`.
- PASS-line delta: `+1601` distinct TestRunner PASS cases.
- Non-overlap: this late `window1.test` dynamic corpus slice avoids the parked window conflict and does not touch accepted window filtered JSON, window pushdown, windowC separator, window4 navigation/value, windowE range/collation, or window7/window8/window9 dynamic files.
- Dependency closure: no new support component is needed; the slice reuses existing native `SQLiteWindowFunction` helpers and the hydrated upstream SQLite test checkout as source truth.
