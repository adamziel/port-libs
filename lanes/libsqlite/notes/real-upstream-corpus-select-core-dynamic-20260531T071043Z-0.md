# real-upstream-corpus-select-core-dynamic-20260531T071043Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T071043Z-0`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`
- Ported sections: `selectB-$ii.23`, `selectB-$ii.24`, and `selectB-$ii.25`.
- Behavior: compound subquery result preservation across `UNION ALL` arms with inner joins, left joins, arithmetic projections, `ORDER BY 1`, and `WHERE y+x NOT NULL` filtering.

## PHP coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectBJoinArithmeticDynamicTest.php`.
- The file adds 1101 distinct TestRunner PASS cases:
  - 1 upstream source citation case.
  - 400 dynamic `selectB-23` inner-join arithmetic compound-subquery cases.
  - 400 dynamic `selectB-24` left-join arithmetic compound-subquery cases, including unmatched `NULL` right-side values.
  - 300 dynamic `selectB-25` filtered arithmetic-sum cases.
- Focused result: `1 test files, 4406 assertions, 0 failures`.

## Non-overlap

This owns the remaining `selectB.test` join-arithmetic compound-subquery cluster and does not repeat earlier accepted `selectB` flattening/order/limit coverage, `selectH` omit-unused coverage, `selectD` parenthesized join coverage, grouped SELECT text, SELECT subqueries, expression `ORDER BY`, JSON table SELECT sources, or metadata-only runner rows.

Mapped denominator coverage remains unchanged because `selectB.test` is already present in the hydrated upstream manifest coverage. Expected selected PASS-line movement is `+1101`.

## Dependency closure

No new support component is needed. This reuses the existing lane-local `SQLiteSelectSql` parser/executor and row-array join/projection behavior against real upstream SQLite SELECT semantics.
