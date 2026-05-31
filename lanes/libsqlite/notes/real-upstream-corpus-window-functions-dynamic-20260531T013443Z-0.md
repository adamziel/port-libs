# real-upstream-corpus-window-functions-dynamic-20260531T013443Z-0

Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`.

Added `SQLiteRealUpstreamWindow4EDynamicBatchTest.php`, a lane-local real upstream
window-function corpus batch derived from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
  sections `1.1-2.4` for ranking, `ntile()`, `lead()`, `lag()`,
  `first_value()`, `last_value()`, and `nth_value()` style value-window
  behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`
  sections `1.2-1.3` and `3.1-5.2` for peer ordering, collation-sensitive
  `RANGE` behavior, and numeric `ROWS`/`RANGE` aggregate edge cases.

The batch adds 1,001 distinct focused TestRunner PASS cases and 13,751 behavior
assertions. It is non-overlapping with the existing window pushdown, window1/2
dynamic frames, window3 matrix, window7/8/9/A/B/C/D, and named
window-function-dynamic files because it combines partitioned ranking summaries,
value frame boundaries, aggregate frame boundaries, exclusion modes, and
windowE-style peer ordering in a fresh deterministic matrix.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow4EDynamicBatchTest.php`
  - `1 test files, 13751 assertions, 0 failures`

Dependency closure: no new support component is needed. The batch reuses the
existing native PHP `SQLiteWindowFunction` implementation and the existing
lane-local TestRunner harness.

Root harness: not run - isolated micro-slice.
