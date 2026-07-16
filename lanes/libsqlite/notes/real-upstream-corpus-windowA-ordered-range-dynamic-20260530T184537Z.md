# Real Upstream WindowA Ordered RANGE Dynamic Corpus

Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260530T184537Z-0`

Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test`
- Exact upstream subtests: `windowA.test` `1.1` through `1.6`, `2.1` through `2.6`, `3.1` through `3.4`, and `4.0`
- Dynamic corpus extension: the same upstream `t1(a,b,d)` rows from `windowA.test` are evaluated over a generated grid of `RANGE` frame start/end boundaries, `ORDER BY d DESC`, and `NULLS FIRST` / `NULLS LAST`.

Coverage added:

- New focused file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindowAOrderedRangeDynamicTest.php`
- Focused result: `1 test files, 3706 assertions, 0 failures`
- PASS-line growth type: real PHP TestRunner PASS-case growth only.
- Mapped denominator growth: none. This ports behavior from an already hydrated upstream script; it does not add runner-map rows.

Non-overlap:

- Avoids accepted `window1`, `window2`, `window3`, `window4`, `window7`, `window8`, and `windowE` dynamic batches.
- This slice focuses on `windowA.test` `ORDER BY d DESC NULLS FIRST/LAST RANGE` behavior and dynamic offset combinations over the upstream rowset.
- It does not add domain-specific APIs, fixture names, examples, or compatibility wrappers.

Dependency closure:

- No new support component is needed. The slice reuses the existing lane-local `SQLiteWindowFunction::aggregateOrderedRangeValues()` behavior and TestRunner harness.
