# real-upstream-corpus-window-functions-dynamic-20260531T103308Z-0

Session: port-dev-sqlite-yield-dyn-real-window-20260531T103308Z
Base accepted HEAD: 1681be96b403cae039655fef5cb4703982266b2d

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections: `window1-52.1`, `window1-52.2`, `window1-52.3`, `window1-52.4`
- Owned behavior: named windows with zero-argument `count()`, `count() OVER ()`, constant `PARTITION BY 6`, and `RANGE BETWEEN 5 PRECEDING AND 0 PRECEDING` over text ordering with `COLLATE binary`.

## Red-First Evidence

Before the source change, the focused reproduction failed:

```text
php -r 'require "tools/bootstrap.php"; use PortLibs\LibSqlite\SQLiteSelectSql; $rows=[["a"=>"AA","b"=>"bb","c"=>356],["a"=>"CC","b"=>"aa","c"=>158],["a"=>"BB","b"=>"aa","c"=>399],["a"=>"FF","b"=>"bb","c"=>938]]; var_export(SQLiteSelectSql::execute("SELECT count() OVER win1 AS count_win1, sum(c) OVER win2 AS sum_win2, first_value(c) OVER win2 AS first_c, count(a) OVER (ORDER BY b) AS count_a FROM t1 WINDOW win1 AS (ORDER BY a), win2 AS (PARTITION BY 6 ORDER BY a RANGE BETWEEN 5 PRECEDING AND 0 PRECEDING)", ["t1"=>$rows]));'
```

Result: fatal `InvalidArgumentException: SQLite SELECT query count() needs a value argument`.

## Implementation

- `SQLiteSelectQuery` now treats zero-argument `count()` window aggregates as `count(*)`, matching SQLite upstream behavior.
- Directly coupled window expectations were rebased to current SQLite behavior for nonnumeric `RANGE` offsets: text keys use the current peer group rather than throwing.
- Default `last_value()` and `nth_value()` text-window expectations were corrected to respect the default frame ending at the current row.

## Focused Coverage

- Added `SQLiteRealUpstreamWindow1NamedCountDynamic20260531Test.php`.
- New focused movement: 1003 distinct TestRunner PASS cases and 6006 assertions.
- Dynamic coverage: 1000 generated real-behavior cases over generic `app_metrics` rows, plus exact checks for upstream `window1.test` sections 52.2, 52.3, and 52.4.

## Non-Overlap

This patch does not repeat the accepted window1/window2 aggregate row batches, window1 chained named-window batches, window1 RANGE offset batches, window3 ranking, window4/window6 value offsets, window7/window8/window9/windowA/windowB/windowC/windowD/windowE dynamic batches, or window error/fault/pushdown batches. It owns only upstream `window1.test` section 52 named zero-argument `count()` window behavior and directly coupled text `RANGE` frame expectation fixes.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1NamedCountDynamic20260531Test.php
php -l lanes/libsqlite/tests/SQLiteSelectAggregateWindowCurrentTest.php
php -l lanes/libsqlite/tests/SQLiteSelectSqlWindowTextTest.php
php -l lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1NamedCountDynamic20260531Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1*Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlWindowTextTest.php lanes/libsqlite/tests/SQLiteSelectAggregateWindowCurrentTest.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
```

Results:

- Syntax checks passed for all changed PHP files.
- New focused corpus test: `1 test files, 6006 assertions, 0 failures`.
- Window1 upstream family: `17 test files, 243361 assertions, 0 failures`.
- Direct window/domain guard group: `4 test files, 215 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `SQLiteSelectSql`, named-window parsing, `SQLiteSelectQuery`, and window-frame evaluation paths.
