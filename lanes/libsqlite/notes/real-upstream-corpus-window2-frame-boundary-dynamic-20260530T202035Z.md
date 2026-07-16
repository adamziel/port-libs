# real-upstream-corpus-window2-frame-boundary-dynamic-20260530T202035Z

Base accepted HEAD: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`.

Added `SQLiteRealUpstreamWindow2FrameBoundaryDynamicBatchTest.php`, a focused
real-upstream window frame-boundary batch from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`.

Upstream scenarios:

- `window2.test` 1.1-1.2 partitioned running windows and whole-frame windows.
- `window2.test` 2.1-2.29 ROWS/RANGE frame boundaries, including oversized
  preceding/following offsets, empty reversed frames, partitioned frames, and
  unbounded endpoints.
- `window2.test` 3.1-3.4 repeated RANGE/ROWS current-source coverage.
- `window2.test` section 4 generated frame-boundary matrix, represented as
  bounded ROWS/RANGE/GROUPS combinations over the same `t1` fixture.

Focused movement:

- New focused PHP TestRunner PASS cases: `1153`.
- New focused assertions: `2305`.
- Expected dashboard movement if accepted: `phpPass` `573146 -> 574299`.
- Mapped denominator: unchanged; this is PHP PASS-line growth over already
  hydrated upstream `window2.test` behavior.

Non-overlap:

- Does not repeat accepted `window5.test` custom function dynamic coverage,
  `window3.test` generated ranking/distribution matrices, `windowE.test`
  total/filter overflow cases, `windowpushd.test` pushdown behavior, or
  `window7` RANGE/GROUPS coverage. This batch owns `window2.test` frame
  boundary aggregate behavior.

Dependency closure:

- No new support component needed. The batch reuses existing
  `SQLiteVdbeWindowAggregateCursor`, numeric/text aggregate helpers, and
  lane-local focused TestRunner infrastructure.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow2FrameBoundaryDynamicBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow2FrameBoundaryDynamicBatchTest.php`
