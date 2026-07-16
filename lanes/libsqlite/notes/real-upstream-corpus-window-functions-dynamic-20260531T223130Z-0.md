# real-upstream-corpus-window-functions-dynamic-20260531T223130Z-0

Base accepted HEAD: `457d8df75c82fef3de304d8652d979a0fd3d1346`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `18.3.1`, `18.3.3`, and `18.3.5`: `string_agg(c, '.') OVER (...)` is an alias of the `group_concat` window aggregate through direct and named windows.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test` section `1.1.14.1`: `string_agg(CAST(b AS TEXT), '.') OVER (ORDER BY a RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)` over the generated `t2` fixture.

Ported behavior:

- `SQLiteSelectSql` now admits `string_agg` as a supported aggregate window function.
- `SQLiteSelectQuery` routes `string_agg` through the same text-window aggregate path as `group_concat`, including default aggregate window frames and explicit separator arguments.
- `SQLiteWindowFunction` accepts `string_agg` anywhere its aggregate frame helpers accept `group_concat`.

Focused evidence:

- Red-first spot check before the source change:
  `php -r "... SQLiteSelectSql::execute(\"SELECT id, string_agg(label, '.') OVER (...)\", ...)"` failed with `SQLite SELECT SQL window function string_agg is not supported`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowStringAggDynamic20260531Test.php`
  passed with `1 test files, 3007 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1ChainedDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicFramesTest.php lanes/libsqlite/tests/SQLiteSelectAggregateWindowCurrentTest.php`
  passed with `3 test files, 6920 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `1 test files, 3 assertions, 0 failures`.
- `php -l` passed for `SQLiteWindowFunction.php`, `SQLiteSelectSql.php`, `SQLiteSelectQuery.php`, and `SQLiteRealUpstreamWindowStringAggDynamic20260531Test.php`.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

- Existing window corpus coverage cited `string_agg` from `window1.test` but routed those cases through `SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', ...)`. This slice adds parser/executor support for actual `string_agg(...) OVER (...)` SQL and verifies alias parity against `group_concat` on real upstream-derived fixtures.

Dependency closure:

- No new support component is needed. The patch reuses the existing `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteWindowFunction` window aggregate execution path.
