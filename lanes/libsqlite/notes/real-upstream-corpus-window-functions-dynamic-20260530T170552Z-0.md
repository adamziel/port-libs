# Real Upstream Corpus Window Functions Dynamic

Slice: `real-upstream-corpus-window-functions-dynamic-20260530T170552Z-0`

Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
  - `window2-1.1` through `window2-1.3`
  - `window2-2.1` through `window2-2.29`
  - `window2-3.1` and `window2-3.3`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
  - `window3-1.1.2.1` and `window3-1.1.2.2` running min/max equivalents
  - `window3-1.1.3.1`
  - `window3-1.1.4.1`
  - `window3-1.1.4.3`

## Coverage Added

Added `SQLiteRealUpstreamCorpusWindowFunctionsDynamicTest.php` with 37 focused
PASS cases and 1389 assertions. The test ports dynamic upstream window behavior
for partitioned and unpartitioned `ROWS`/`RANGE` frame boundaries, empty and
reversed frames, running aggregate frames, `row_number()`, `dense_rank()` peer
groups, running `min()`/`max()`, and frame-sensitive `first_value()`,
`last_value()`, and `nth_value()`.

This slice is non-overlapping with the existing static
`SQLiteWindowFrameBoundaryCorpusTest.php` because it uses the hydrated upstream
`window2.test` and `window3.test` row sets and expected result sequences rather
than synthetic boundary-only rows.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamicTest.php`
  - `1 test files, 1389 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses existing native
`SQLiteWindowFunction` frame, aggregate, ranking, and value-function helpers.
