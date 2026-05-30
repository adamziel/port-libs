# real-upstream-corpus-window-functions-dynamic-20260530T214757Z-0

- Added `SQLiteRealUpstreamWindow3AggregateExecutorDynamicTest.php`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`.
- Covered upstream sections:
  - `window3.test:1.0` 191-row `t2(a INTEGER PRIMARY KEY, b INTEGER)` fixture.
  - `window3.test:1.1` `max(b) OVER (ORDER BY a)`.
  - `window3.test:1.1.2` explicit `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW` min/max frames.
  - `window3.test:1.1.6` aggregate-window executor variants for `sum`, `avg`, `total`, and `count`, including partition expressions derived from `b%10` and `b%2`.
- Focused assertion growth: 1,912 assertions and 1,911 PASS lines in the new selected file, including 1,910 distinct upstream-row behavior PASS cases.
- Non-overlap: this uses `SQLiteSelectQuery` executor materialization over the real `window3.test` aggregate-window fixture. It does not repeat the existing direct `SQLiteWindowFunction` ranking/distribution batches, `window4` value-function matrix, `window5` custom-window coverage, `window6` value/default-frame coverage, `window7/window8/window9/windowA-windowE` frame/collation coverage, or JSON/WAL/B-tree/VFS corpus slices.
- Dependency closure: no new support component is needed; this reuses existing native `SQLiteSelectQuery`, `SQLiteSelectExpression`, `SQLiteSelectResult`, and window aggregate frame support.
- Root harness: not run - isolated micro-slice.
