# Real Upstream Corpus: Window Fractional RANGE

Slice: `real-upstream-corpus-window-functions-dynamic-20260530T170122Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test`
- Ported scenario IDs: `windowA.test 1.1`, `1.2`, `1.3`, `1.4`, `1.5`, `1.6`, `2.1`, `2.2`, `2.3`, `2.4`, `2.5`, `2.6`, `3.1`, `3.2`, `3.3`, `3.4`, and `4.0`.

Behavior covered:

- Fractional `RANGE BETWEEN` frame boundaries over descending numeric order keys.
- Current-row, preceding, following, and unbounded boundary combinations.
- Frame membership plus `group_concat`, `sum`, `total`, `avg`, `min`, `max`, and `count` over the same window frames.
- Strict guards for malformed fractional boundaries and unsupported `NULL` range order keys in the current bounded helper.

Non-overlap:

- Existing accepted window coverage already covered `window2.test`, `window3.test`, and `window4.test` ROWS/RANGE/GROUPS frame and offset behavior.
- This slice targets `windowA.test` fractional numeric `RANGE` cases, especially descending-order boundary behavior, and does not touch JSON table windows, named-window subqueries, no-ORDER-BY frame rejection, or prior ROWS/GROUPS dynamic batches.

Evidence:

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFractionalRangeDynamicTest.php`
- Result: `1 test files, 700 assertions, 0 failures`
- Dashboard expectation: `phpPass` +700, mapped coverage unchanged because no new denominator row is claimed.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLiteWindowFunction` frame evaluator and adds row-level frame evidence through `aggregateFrameBetweenRows()`.
