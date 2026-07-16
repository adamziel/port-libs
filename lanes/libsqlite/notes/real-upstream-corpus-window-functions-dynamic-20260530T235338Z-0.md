# real-upstream-corpus-window-functions-dynamic-20260530T235338Z-0

Status: ready lane patch for real upstream window-function-adjacent FILTER corpus growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filter2.test`

Ported behavior:

- `filter1.test` `1.1-1.8`: aggregate `FILTER (WHERE ...)` sum/min/max/count
  behavior, including empty filtered aggregate NULL handling.
- `filter1.test` `5.1-5.3`: derived-row `count(*) FILTER (...)` and windowed
  filtered count behavior over full and ordered running frames.
- `filter2.test` `1.1-1.15`: larger generated aggregate FILTER matrix over
  NULL inputs, DISTINCT counts, grouped filters, HAVING-style filtered sums,
  string aggregation, and filtered AVG-style numeric buckets.

New focused coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamFilterWindowDynamicTest.php`
- 1,006 focused TestRunner PASS cases.
- 8,015 behavior assertions.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterWindowDynamicTest.php
# 1 test files, 8015 assertions, 0 failures
```

Non-overlap:

This slice avoids the accepted `windowA`/`windowB`/`windowC`/`windowD`/`windowE`,
`window7`, `window9`, `windowpushd`, and `windowerr` real-corpus batches. It
uses real upstream `filter1.test` and `filter2.test` sections and exercises the
existing libsqlite numeric, text, and aggregate-window FILTER helpers with a
dynamic predicate grid. It does not add metadata-only runner rows, generated
fake upstream names, WordPress-specific APIs, or dashboard-only counter edits.

Dependency closure:

No new support component is needed. The batch reuses existing bounded native
PHP aggregate helpers and `SQLiteWindowFunction::aggregateFrameBetweenValues()`.
The next useful batch should target parser-level SQL execution of these FILTER
forms if the integrator wants to move from helper parity to SELECT-text parity.
