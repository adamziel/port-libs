# Real Upstream Window Error Dynamic Slice

Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260531T034112Z-0`

Base accepted HEAD: `ca2d3c3a4732734353ce27d70067c3ae40d81496`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test`
- Covered sections: `1.1-1.8`, `2.1-2.2`, `3.0`, `3.2`, `3.3`

Behavior added:

- `SQLiteSelectSql` now rejects `RANGE` offset frames unless there is exactly one `ORDER BY` expression, matching `windowerr.test 1.7`.
- `SQLiteSelectSql` now rejects arguments passed to no-argument ranking window functions (`row_number`, `rank`, `dense_rank`, `percent_rank`, `cume_dist`), matching `windowerr.test 3.3`.
- Focused tests also preserve existing upstream rejection behavior for negative/non-numeric frame offsets, nested aggregate/window misuse, and aggregate `ORDER BY` alias misuse.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowErrDynamicCorpusTest.php`
- Result: `1 test files, 236 assertions, 0 failures`
- PASS-line count: 234 focused cases

Non-overlap:

- Avoids accepted `window2`/`window3` dynamic behavior and the existing `window4`, `window5`, `window6`, `window7`, `window8`, `window9`, `windowA` through `windowE`, `windowfault`, and `windowpushd` dynamic corpus files.
- The attempted obvious `window4`/`window5` material was already covered; this slice instead fixes concrete `windowerr.test` parser gaps.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql` window parsing and the existing `TestRunner`.

Floor note:

- This handoff is smaller than the preferred 500+ assertion corpus floor. It is behavior-backed and should be accepted only as a named upstream parser/runtime rejection fix, not as bulk throughput.
