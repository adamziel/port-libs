# real-upstream-corpus-window-functions-dynamic-20260601T013157Z-0

Base accepted HEAD: `e0cca2a185669ab1c0c1e83b7ad9894e29901028`

Added `SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T013157ZTest.php`,
a real upstream dynamic corpus for the tail of `window4.test` section 4.5.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
  sections `4.5.33` through `4.5.57`: paired `ROWS` frames over
  `PARTITION BY b ORDER BY a`, including empty preceding frames and wide
  following frames.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
  sections `4.5.58` through `4.5.73`: paired `RANGE UNBOUNDED PRECEDING`
  frames that mix ascending, descending, omitted, and `ORDER BY b, a` order
  definitions.

Focused movement:

- New focused TestRunner PASS cases: `1043`
- Focused assertions: `4752`
- Expected selected `phpPass` movement: `5239070 -> 5240113`
- Mapped denominator coverage: unchanged, already `1589 / 1589`

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T013157ZTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 4752 assertions, 0 failures
```

Non-overlap:

This slice targets `window4.test` 4.5.33-4.5.73 tail behavior. It avoids the
accepted `window4` ntile/lead/lag/nth_value batches, `windowE` overflow and
real-range batches, `windowA` NULL/DESC range batches, `window8` fractional
range batches, `windowD` truth/view batches, parser-level SELECT SQL window
text coverage, JSON window coverage, and earlier `window4.test` 4.5.1-4.5.32
batches.

Dependency closure:

No new support component is needed. The slice reuses lane-local
`SQLiteWindowFunction::aggregateFrameBetweenValues()` and
`SQLiteWindowFunction::aggregateOrderedRangeValues()` with independent PHP
oracles against the hydrated upstream `window4.test` tail sections.
