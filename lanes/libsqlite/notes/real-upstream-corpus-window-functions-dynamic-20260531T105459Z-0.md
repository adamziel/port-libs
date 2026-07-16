# real-upstream-corpus-window-functions-dynamic-20260531T105459Z-0

Base accepted HEAD: `1050199a8fd43430a4d0f31b8acaf48bdfe1ca42`

Status: ready focused behavior patch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test`
  - `1.3`: multiple `sum(a) FILTER (WHERE ...)` thresholds in one SELECT.
  - `1.4` through `1.7`: filtered `max`, `min`, `count(*)`, and grouped filtered `min`.
  - `3.1`, `3.3`, and `3.5`: filtered `max()` no-match and grouped row behavior.
  - `4.1` through `4.4`: filtered `avg()` with grouped `ORDER BY` alias, expression, aggregate, and ordinal.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filter2.test`
  - `1.9` through `1.12`: filtered aggregates in grouped `HAVING` and `ORDER BY` paths.

Implementation:

- Fixed `SQLiteSelectSql` grouped/implicit aggregate planning so parsed ordinary aggregate `FILTER (WHERE ...)` predicates are materialized as distinct filtered aggregate specs instead of collapsing to the unfiltered base `sum`/`count`/`avg` summary columns.
- Extended `SQLiteSelectQuery` and `SQLiteGroupedAggregate` to execute those filtered aggregate specs over group rows, including scalar aggregate arguments, `count(*)`, `count(DISTINCT ...)`, `HAVING`, and aggregate `ORDER BY` rewrite paths.
- Added `SQLiteRealUpstreamFilterAggregateSelectSqlDynamic20260531Test.php` with 8 exact upstream section tests, 1000 dynamic upstream-shaped SELECT/GROUP/HAVING cases, and source/dependency assertions.

Red-first evidence:

- Before the source fix, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterAggregateSelectSqlDynamic20260531Test.php` failed with `1 test files, 10 assertions, 1008 failures`; the first failure showed `sum(a) FILTER (WHERE a>8)` returning the unfiltered `45` instead of `9`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`: no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteGroupedAggregate.php`: no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamFilterAggregateSelectSqlDynamic20260531Test.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterAggregateSelectSqlDynamic20260531Test.php`: `1 test files, 6012 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterWindowDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownFilterDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowGroupsFilterDynamic20260531Test.php`: `3 test files, 13025 assertions, 0 failures`.

Non-overlap:

This slice does not repeat accepted `window1` named count, `window7` GROUPS/RANGE, `windowC` separator, `windowpushd` predicate preservation, `filter1`/`filter2` helper-level aggregate coverage, JSON aggregate window coverage, or runner metadata. It owns the missing parser-level `SQLiteSelectSql` execution gap where ordinary aggregate `FILTER` predicates from upstream `filter1.test`/`filter2.test` were parsed but ignored for grouped and implicit aggregates.

Expected dashboard movement:

- `phpPass +1010` from the new focused test file.
- `benchmarkDenominator.mapped` remains `1589 / 1589`; this is real upstream behavior growth over already mapped `filter1.test` and `filter2.test` inventory.

Dependency closure:

No new support component is needed. The patch reuses lane-local `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteGroupedAggregate`, `SQLiteSelectExpression`, and `SQLiteSelectPredicate` execution.

Exclusions:

- No root harness was run in this isolated micro-slice.
- No example smoke was added because the slice is generic parser/executor behavior and the current libsqlite rule rejects new WordPress-specific API or smoke surfaces.
- A broader exploratory run of older `SQLiteSelectWindow*CurrentNext*` files still has unrelated stale failures around window frame plan-shape expectations and CASE filter parsing; those files are not part of the ready evidence for this aggregate FILTER execution patch.
