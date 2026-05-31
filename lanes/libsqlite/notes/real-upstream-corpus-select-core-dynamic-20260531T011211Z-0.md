# real-upstream-corpus-select-core-dynamic-20260531T011211Z-0

Base accepted HEAD: `87abcd98ff24a32f5554f16930fc2af1462cc57c`.

Ported real upstream SQLite SELECT core behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test`
- `selectE-3.1`: a compound SELECT must reject `ORDER BY` attached before a following compound operator. Upstream reports `ORDER BY clause should come after EXCEPT not before`.

Implementation movement:

- `SQLiteSelectSql::compoundSqlPlan()` now checks every non-final compound arm for tail `ORDER BY` or `LIMIT` clauses and rejects them before planning the arm.
- The error message names the following compound operator, preserving the upstream `selectE-3.1` shape for `EXCEPT` and extending the same parser rule to `UNION`, `UNION ALL`, and `INTERSECT`.

Focused PHP coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectECompoundOrderErrorDynamicTest.php`.
- 1,001 distinct TestRunner PASS cases.
- 4,003 focused behavior assertions.
- Dynamic generic row-free SELECT SQL varies literal values, collation names, ordinal spellings, and following compound operators across 1,000 seeds.

Non-overlap:

- This owns only the `selectE-3.1` misplaced compound-tail `ORDER BY` parser error.
- It does not repeat accepted `selectE` compound collation result ordering, `selectF` copy-register compound ordering, `selectC` alias resolution, `selectD` parenthesized join/derived aggregate behavior, `selectH` omit-unused/empty-right UNION coverage, negative LIMIT, grouped SELECT text, expression ORDER BY, JSON table cursor/source/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `selectE.test` is already part of the hydrated upstream manifest coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectECompoundOrderErrorDynamicTest.php`
  - Result: `1 test files, 4003 assertions, 0 failures`
  - PASS lines: `1001`

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql` parser, compound SELECT planner, tail-clause parsing, and exception diagnostics.
