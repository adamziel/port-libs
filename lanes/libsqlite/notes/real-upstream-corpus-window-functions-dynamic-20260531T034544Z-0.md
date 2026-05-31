# real-upstream-corpus-window-functions-dynamic-20260531T034544Z-0

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T034544Z-0`.

Added `SQLiteRealUpstreamWindow1SubqueryFilterDynamicTest.php`, a focused dynamic port of real upstream `window1.test` correlated window subquery behavior:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` section `10.7`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` section `10.8`

Behavior covered:

- Full-partition `sum(total)` window aggregation inside a correlated scalar subquery.
- Correlated outer-row concatenation after the inner window aggregate is evaluated.
- `FILTER (WHERE sales.emp!=outer.emp)` on a full `RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING` frame.
- 1000 dynamic row-order/name/total mutations that preserve the upstream correlated-subquery and filtered-window invariants.

Non-overlap:

- Does not repeat accepted `window1.test` sections `1.*`, `4.*`, `5.4`, `7.2-7.4`, `10.1-10.6`, `13.*`, or `36.*`.
- Does not repeat accepted `window2`/`window3`, frame-boundary, ordered RANGE/value, JSON-object inverse, group-concat, boolean-view, windowfault, pushdown, JSON table window ranking, grouped SELECT text, expression ORDER BY, or root-gate RANGE/GROUPS validation coverage.
- Adds no metadata-only runner rows and no fabricated upstream script ids.

Focused evidence:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryFilterDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryFilterDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryFilterDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 5003 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass +1003` focused TestRunner PASS lines.
- `benchmarkDenominator.mapped` unchanged at `1589 / 1589`; this is behavior growth over already mapped upstream `window1.test`.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteWindowFunction::aggregateFrameBetweenValues()` full-frame RANGE and FILTER support.
