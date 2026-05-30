# real-upstream-corpus-window-functions-dynamic-20260530T203011Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test`

Ported behavior:

- `windowerr.test:1.1-1.6` negative `ROWS`, `RANGE`, and `GROUPS`
  frame-boundary rejection.
- `windowerr.test:3.0` and `3.2` non-numeric frame-boundary rejection.
- `windowerr.test:3.3` non-aggregate window function rejection through the
  aggregate-window dispatch surface.
- Dynamic bad-function and `nth_value()` bad-index matrices exercise the same
  upstream error class against varied row values and frame expressions.

Focused count:

- New focused file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamWindowErrDynamicTest.php`.
- Verified distinct TestRunner PASS cases/assertions: 1155.

Non-overlap:

- This targets upstream `windowerr.test` validation behavior only. It avoids
  accepted `window1` through `windowE`, `windowpushd`, JSON window, SQL text
  grouped/window, VFS/WAL/B-tree, source-neutral cleanup, and suite-runner
  metadata surfaces.

Dependency closure:

- No new support component is needed. The existing `SQLiteWindowFunction`
  bounded frame parsers and aggregate/value dispatch validators are reused.
