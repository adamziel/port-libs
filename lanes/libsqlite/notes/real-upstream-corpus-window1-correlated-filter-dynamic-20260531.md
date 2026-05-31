# Real Upstream Corpus Window1 Correlated Filter Dynamic - 2026-05-31

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T054353Z-0`

Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Scenario: `window1.test 10.8`, correlated outer-row `FILTER` inside a whole-partition `sum(total) OVER (ORDER BY total RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING)`.

Coverage added:

- `SQLiteRealUpstreamWindow1CorrelatedFilterDynamic20260531Test.php`
- 8 exact base-row cases matching the upstream sales table expected output.
- 1,000 dynamic correlated-filter cases that vary row totals, selected outer row, and generated employee identities while preserving the upstream whole-frame filtered aggregate shape.
- 4,018 focused behavior assertions.
- 1,010 distinct TestRunner PASS cases.

Non-overlap:

- Existing regional sales window coverage in `SQLiteRealUpstreamWindow1RegionalSalesDynamicTest.php` covers `window1.test 10.1-10.6`.
- This handoff owns only `window1.test 10.8`, the correlated outer-row `FILTER` case not covered by that file.
- It does not touch accepted JSON table, WAL, B-tree, VFS, PRAGMA, expression ORDER BY, grouped SELECT, or source-neutral API cleanup surfaces.

Dependency closure:

- No new support component needed.
- Reuses `SQLiteWindowFunction::aggregateFrameBetweenValues()` for `FILTER` handling over a whole `RANGE` frame.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1CorrelatedFilterDynamic20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1CorrelatedFilterDynamic20260531Test.php`
  - `1 test files, 4018 assertions, 0 failures`
  - 1,010 PASS lines

Root harness:

- Not run; isolated micro-slice.
