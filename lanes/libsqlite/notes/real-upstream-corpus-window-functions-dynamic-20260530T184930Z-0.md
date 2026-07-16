# real-upstream-corpus-window-functions-dynamic-20260530T184930Z-0

Accepted base: `133a53d7c328acb7a2a9f5b43747e45d705421ba`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test`
- Direct sections: `1.1-1.8`, `2.1.1-2.1.4`, `3.2`, `3.4`, `3.5c`, `3.9`, `3.10`, `3.14`, `3.16`, `7.1-7.4`, `9.0`, `10.2-10.3`, `11.3-11.10`.
- Coupled prior local section updated for changed behavior: `windowA.test` fractional RANGE NULL-key guard now expects SQLite peer fallback instead of a rejection.

Behavior implemented:

- `SQLiteWindowFunction::frameIndexesBetween()` now treats nonnumeric `RANGE` ORDER keys as peer-group frames even when the frame uses `PRECEDING` or `FOLLOWING` offsets, matching upstream `windowB.test` NULL/text/blob RANGE behavior.
- `SQLiteJsonAggregate::jsonGroupObject()` and DISTINCT object aggregation omit NULL labels instead of throwing, matching upstream `json_group_object(if(...), v)` window inverse cases.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowBJsonRangeDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFractionalRangeDynamicTest.php`
- Result: `2 test files, 10856 assertions, 0 failures`.
- New file PASS cases: 15.
- Real behavior assertion floor: satisfied by `>5000` assertions from upstream `windowB.test` scenarios.

Non-overlap:

- Avoids accepted dynamic window batches for `window1/window3/window4/window7/window8/windowE/windowfault` and the existing `windowA` corpus files.
- Does not add metadata-only runner rows, fabricated `.test` ids, WordPress-shaped APIs, or compatibility wrappers.

Dependency closure:

- No new support component is required. The slice reuses existing native PHP window-frame and JSON aggregate helpers.
