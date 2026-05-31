# real-upstream-corpus-window-functions-dynamic-20260531T063006Z-0

Added a non-overlapping real upstream window-function corpus extension to
`SQLiteRealUpstreamWindowFunctionsDynamicTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
  sections `window4-1.1` through `window4-1.19`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test`
  sections `window7-1.2` through `window7-1.8.2`.

Focused coverage:

- 19 focused TestRunner PASS cases for `ntile(1)` through `ntile(19)` over the
  upstream ten-row letter table, with 190 row-level assertions.
- 8 focused TestRunner PASS cases for upstream `GROUPS` and `RANGE` peer-frame
  aggregation over the upstream one-hundred-row modulo peer set, with 800
  row-level assertions.
- Total new focused growth: 27 TestRunner PASS cases and 990 behavior
  assertions.

Non-overlap:

- Existing window corpus coverage already covered `window1`, `window2`,
  `window6`, and `windowE` sections plus generic ranking/value/filter cases.
- This slice does not repeat accepted grouped SELECT text, JSON table source
  wiring, B-tree page move/root collapse, WAL checkpoint/savepoint, VFS writer,
  source-neutral API cleanup, or metadata-only upstream runner rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFunctionsDynamicTest.php`
  passed: `1 test files, 1085 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The batch reuses the existing native
  `SQLiteSelectSql` window executor and the hydrated upstream SQLite corpus as
  source truth.
