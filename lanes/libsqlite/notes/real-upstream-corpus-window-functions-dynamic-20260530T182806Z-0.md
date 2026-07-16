# Real Upstream Window Functions Dynamic Batch

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T182806Z-0`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
- Ported scenarios:
  - `window2.test` section `4.1` / `4.2`: 200-row `t2` corpus with `PARTITION BY (b%10) ORDER BY b` cumulative `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW` behavior.
  - The focused PHP test checks `sum`, `count`, `total`, `avg`, `min`, and `max` over every upstream row, for 1,200 distinct TestRunner cases.
- New focused test file:
  - `lanes/libsqlite/tests/SQLiteRealUpstreamWindow2PartitionRangeDynamicTest.php`
- Non-overlap:
  - Avoids the accepted prior dynamic window batch over `window3.test`/`window4.test` ranking, `ntile`, `lead`, `lag`, and `nth_value` coverage.
  - Avoids SQL text GROUPS/RANGE frame rejection work and existing frame-boundary helper vectors.
  - This slice owns the real upstream `window2.test` section-4 partitioned cumulative RANGE aggregate corpus.
- Dependency closure:
  - No new support component needed. The test reuses native `SQLiteWindowFunction::aggregateFrameBetweenValues()` and an independent lane-local oracle.
