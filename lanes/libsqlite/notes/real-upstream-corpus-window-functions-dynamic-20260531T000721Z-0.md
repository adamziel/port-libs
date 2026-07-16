# real-upstream-corpus-window-functions-dynamic-20260531T000721Z-0

Base accepted HEAD: `88eb6ac3e2ad25d5a4756e5a167672b605fd3e97`.

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`.

Ported sections:

- `windowpushd.test` 1.2 and 1.3: partitioned `row_number()` view rows and `WHERE grp_id=2` after window evaluation.
- `windowpushd.test` 2.0/2.1.1.1 and 2.0/2.1.1.2: partitioned `max(c)` window view and `a IN ('A','B')` filtering.
- `windowpushd.test` 2.0/2.1.3.1 and 2.0/2.1.3.5: partitioned `max(d)` plus `row_number()` view rows and post-window `d<0.55` filtering.
- `windowpushd.test` 2.0/2.1.4.1 through 2.0/2.1.4.3: grouped aggregate rows feeding `max(max(z)) OVER (PARTITION BY sum(y))`, then filtering by grouped sum.

Focused movement:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownExactDynamicTest.php`.
- Focused TestRunner result: `1 test files, 17010 assertions, 0 failures`.
- PASS-line delta: `+1010` distinct TestRunner PASS cases.
- `phpPass` in `lane-status.json`: `1292330 -> 1293340`.
- Mapped denominator remains `1589 / 1589`.

Non-overlap:

This slice avoids the existing accepted window1/window2 frame cases, windowD truthiness, windowE numeric RANGE/total/sum cases, window7/window9/windowerr coverage, JSON table window ranking, grouped SELECT text, and expression ORDER BY clusters. It targets the distinct upstream push-down optimization file by asserting that window values are computed over the view/subquery rowset before outer filters are applied.

Dependency closure:

No new support component is needed. The tests reuse existing lane-local `SQLiteWindowFunction` row-number and aggregate frame helpers and plain PHP row filtering to model the upstream view/subquery boundaries.
