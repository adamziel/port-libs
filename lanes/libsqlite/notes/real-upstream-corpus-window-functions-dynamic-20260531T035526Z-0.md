# real-upstream-corpus-window-functions-dynamic-20260531T035526Z-0

Base accepted HEAD: `9995fe4897b08d71e2d75db489dfa08c480a5292`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test` sections `1.1-1.19`, `2.1-2.4.1`, and `3.1-3.6.3`.

Behavior ported:

- `ntile()` bucket sizing for ten-row and dynamic rowsets.
- `nth_value()` with per-row index values over the default prefix frame.
- `lead()` / `lag()` offsets and default values.
- `group_concat()` over `ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING`.
- `FILTER` plus empty, point, preceding, and following `ROWS` frames for aggregate/value window behavior.

Focused coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindow4NavigationDynamicTest.php`.
- 1,029 distinct TestRunner PASS cases when run in isolation.
- Dynamic cases are generated from the same `window4.test` navigation/frame semantics and avoid the existing `window1/window2`, `window2` large dynamic, `window9`, and `window12` real-upstream window corpus files.

Dependency closure:

- No new support component needed. The slice reuses existing `SQLiteWindowFunction` helpers for navigation, ranking, frame aggregation, SQL truth filters, and dynamic prefix-frame `nth_value()` behavior.

Root harness:

- Not run - isolated micro-slice.
