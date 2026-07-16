# real-upstream-corpus-window-functions-dynamic-20260530T171102Z

Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`.

Added `SQLiteRealUpstreamWindowFunctionsDynamicTest.php`, a generic application
port of selected upstream SQLite window behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
  scenarios `1.1`-`1.5`, `4.1`-`4.10`, and invalid `ntile()` guards
  `5.1`-`5.2`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
  scenarios `1.1`-`1.3` and bounded `ROWS` scenarios `2.1`-`2.15`.

Focused result:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFunctionsDynamicTest.php`
- `1 test files, 60 assertions, 0 failures`

Non-overlap:

This batch does not touch accepted static `GROUPS`/`RANGE` guard coverage,
window cursor internals, JSON table windows, compound recursive window slices,
or prior `window2/window3` frame files. It adds a separate dynamic
`SQLiteSelectSql` upstream corpus file using source-neutral `app_*` table names.

Dependency closure:

No new support component is needed. The batch reuses existing lane-local
`SQLiteSelectSql`, `SQLiteSelectQuery`, aggregate window, ranking, value
function, named-window, and bounded frame execution.

Follow-up surfaced:

- scalar expressions around window results, such as `4 + sum(b) OVER ()`;
- end-bound `PRECEDING` frames;
- explicit `group_concat(value, separator)` separators in window context;
- SQLite's implicit partition-output order for no-outer-`ORDER BY` window
  queries.
