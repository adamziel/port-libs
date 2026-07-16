# real-upstream-corpus-select-core-dynamic-20260531T013402Z-0

Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`.

Ported real upstream SQLite SELECT core behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`
- `selectC-4.2`: `SELECT a FROM (SELECT DISTINCT a, b FROM t_distinct_bug)` preserves one output row for each distinct inner `(a,b)` pair even though the outer projection only reads `a`.
- `selectC-4.2b`: the same distinct-derived result survives view-like reuse.

Focused PHP coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCDistinctDerivedDynamicTest.php`.
- 3,003 distinct TestRunner PASS cases.
- 12,008 focused behavior assertions.
- Dynamic generic application row sets vary duplicated inner `(tenant_id,key_group)` pairs, duplicate counts, filtered derived input, lexical text ordering, and `LIMIT`/`OFFSET` over the derived distinct projection across 1,000 seeds.

Non-overlap:

- This owns the residual `selectC-4.2`/`selectC-4.2b` distinct-derived projection behavior.
- It does not repeat prior `selectC` alias WHERE/HAVING/ORDER coverage, `selectD` parenthesized join alias batches, grouped SELECT text, expression ORDER BY, JSON table cursor/source/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `selectC.test` is already part of the hydrated upstream manifest coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCDistinctDerivedDynamicTest.php`
  - Result: `1 test files, 12008 assertions, 0 failures`
  - PASS lines: `3003`

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql`, derived table, `DISTINCT`, text `ORDER BY`, and `LIMIT`/`OFFSET` execution.
