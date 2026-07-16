# real-upstream-corpus-window-functions-dynamic-20260530T193321Z-0

Behavior batch: ported real upstream SQLite `test/window2.test` frame-boundary
sections `2.1` through `2.30` into focused PHP TestRunner coverage.

Coverage:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`.
- Fixture: `window2.test` section `1.0`, six-row `t1(a,b,c,d)` table.
- Ported scenarios: `window2.test` sections `2.1` through `2.30`, covering
  `ROWS` and `RANGE` windows with large finite bounds, current-row bounds,
  preceding-only, following-only, unbounded-following, empty frames, `PARTITION
  BY b`, `PARTITION BY a%2`, numeric `ORDER BY d`, and text `ORDER BY b`.
- Focused assertions/PASS lines: `1045` distinct TestRunner assertions, one per
  row/function/frame result plus one upstream citation assertion.
- Aggregate functions covered over each admitted frame: `sum`, `count`,
  `total`, `avg`, `min`, and `max`.

Non-overlap:

- This is not the previously accepted window RANGE/NULL placement,
  ranking/distribution, `windowA`, `windowB`, `window5`, `windowC`, ordered
  RANGE/value/dynamic/pushdown/group-concat coverage. It specifically targets
  `window2.test` frame-boundary sections `2.1-2.30`.
- No production API, metadata-only admission rows, generated fake upstream
  script ids, WordPress-named APIs, examples, or compatibility wrappers were
  added.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow2FrameBoundariesDynamicBatchTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow2FrameBoundariesDynamicBatchTest.php`
  - `1 test files, 1045 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure:

- No new support component is needed. This reuses lane-local
  `SQLiteWindowFunction::aggregateFrameBetweenValues()` behavior and the
  existing PHP TestRunner harness.
