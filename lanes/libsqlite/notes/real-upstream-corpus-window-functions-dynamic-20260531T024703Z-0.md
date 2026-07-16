# real-upstream-corpus-window-functions-dynamic-20260531T024703Z-0

Implemented a real upstream `windowpushd.test` dynamic corpus slice.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`
- Covered sections:
  - `windowpushd.test` `1.2`: partitioned `row_number()` source output.
  - `windowpushd.test` `1.3`: outer equality filter over a partition key.
  - `windowpushd.test` `2.0-2.3`: `max(...) OVER (PARTITION BY ...)` and
    `row_number() OVER (PARTITION BY ...)` behind outer equality/range filters.

Changed files:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownSqlDynamicTest.php`.

Evidence:

- Red-first: initial focused run exposed two bounded blockers:
  - the oracle incorrectly sorted no-ORDER window output by partition instead
    of preserving upstream scan order while computing partition-local row
    numbers;
  - `windowpushd.test` `2.4` requires grouped derived SELECTs with multiple
    aggregate value columns, which is a broader grouped-aggregate executor
    blocker and was excluded from this pushdown-window slice.
- Final focused run:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownSqlDynamicTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownSqlDynamicTest.php`
  - Result: `1 test files, 5405 assertions, 0 failures`.

Non-overlap:

This does not repeat accepted `window1`, `window2`, `window3`, `window4`,
`window5`, `window6`, `window7`, `window8`, `window9`, `windowA`, `windowB`,
`windowC`, `windowD`, `windowE`, `windowerr`, or `windowfault` coverage. It
owns `windowpushd.test` filtered partition-window behavior through
`SQLiteSelectSql` derived-table execution. It does not add metadata-only
runner rows or domain-specific APIs.

Dependency closure:

No new support component is needed. The slice reuses native `SQLiteSelectSql`
derived-table execution and window projection support. Follow-up blocker:
`windowpushd.test` `2.4` needs grouped derived SELECT execution with multiple
numeric aggregate value columns, not just the current single-value-column
summary model.
