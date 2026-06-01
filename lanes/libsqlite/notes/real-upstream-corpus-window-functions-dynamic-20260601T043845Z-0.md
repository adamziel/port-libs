# real-upstream-corpus-window-functions-dynamic-20260601T043845Z-0

Base accepted HEAD: `a9f4989344098e67e1082ce806a8270acd26ace6`

Implemented a real upstream window-function scalar aggregate subquery batch
under `lanes/libsqlite/**`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
- Ported section: `window4.test` `12.2`.

Behavior admitted:

- `SELECT (SELECT avg(a)) FROM t2 ORDER BY 1` treats the aggregate in the
  scalar subquery as an aggregate over the outer source rows and returns one
  collapsed result row.
- The lift is narrow: it only applies to a simple scalar subquery with no inner
  `FROM` and a single aggregate over an outer source column.
- Compound scalar subqueries and window scalar subqueries remain on their
  existing path, preserving the accepted `window4.test` `12.1` and `12.3`
  behavior.

Focused movement:

- New focused TestRunner PASS cases: `1003`.
- New focused behavior assertions: `4010`.
- Expected selected `phpPass` movement: `5478060 -> 5479063`.
- Mapped denominator coverage: unchanged at `1589 / 1589`.

Red-first evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow4ScalarAggregateSubqueryDynamic20260601Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1008 assertions, 1001 failures
```

Passing focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow4ScalarAggregateSubqueryDynamic20260601Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 4010 assertions, 0 failures
```

Adjacent regression evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T025508ZTest.php lanes/libsqlite/tests/SQLiteSelectSqlWindowTextTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 18098 assertions, 0 failures
```

Non-overlap:

- This completes the explicit `window4.test` `12.2` exclusion from the
  accepted `real-upstream-corpus-window-functions-dynamic-20260601T025508Z-0`
  slice.
- It does not repeat `window4.test` `11.1`, `11.5`, `11.7`, `11.8`, `12.1`,
  or `12.3`, earlier `window4.test` frame/value batches, JSON table/window
  work, grouped SELECT text, expression `ORDER BY`, WAL/VFS/B-tree storage
  slices, or metadata-only runner rows.

Dependency closure:

- No new support component is needed. The implementation reuses
  `SQLiteSelectSql` scalar subquery parsing and existing implicit aggregate
  summary execution.
