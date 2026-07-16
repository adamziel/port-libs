# real-upstream-corpus-select-core-dynamic-20260531T034439Z-0

Base accepted HEAD: `ca2d3c3a4732734353ce27d70067c3ae40d81496`.

Ported a focused real upstream SELECT core behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- `select4-2.5`: `ORDER BY` scalar subqueries can reference visible result
  aliases from the surrounding constant SELECT.

Implementation:

- Scoped `SQLiteSelectProjection` so hidden planner-added `ORDER BY`
  expressions evaluate with already-projected visible aliases available.
- This preserves normal visible projection evaluation while letting
  planner-added hidden order expressions match SQLite alias visibility.

Focused movement:

- Added `SQLiteRealUpstreamSelect4OrderByScalarSubqueryDynamicTest.php`.
- Expected PASS lines: `+3002`.
- Expected behavior assertions: `+12008`.

Non-overlap:

- This owns the residual `select4-2.5` scalar-subquery `ORDER BY` alias
  behavior.
- It avoids prior `select4` compound/operator/VALUES/coroutine/aggregate-join
  dynamic batches, accepted SELECT subqueries, grouped SELECT text, expression
  `ORDER BY`, JSON table SELECT source/cursor/constraint work, VFS/WAL/B-tree
  surfaces, and metadata-only runner rows.
- Mapped denominator remains unchanged because `select4.test` is already in the
  hydrated upstream manifest.

Dependency closure:

- No new support component is needed.
- Reuses the native `SQLiteSelectSql` parser/executor and existing scalar
  subquery machinery.
