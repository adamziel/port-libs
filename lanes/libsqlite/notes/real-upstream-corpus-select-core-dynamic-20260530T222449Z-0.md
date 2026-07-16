# real-upstream-corpus-select-core-dynamic-20260530T222449Z-0

Base accepted HEAD: `9f789d799d368a95f9314c9ed366646dd5d17143`.

Ported real upstream SQLite SELECT core behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- `selectD-4.1`: derived aggregate subquery under `LEFT JOIN`, where the derived subquery reads from an aliased parenthesized join group.

Implementation movement:

- Fixed `SQLiteSelectSql` so explicit aliases on parenthesized join groups expose the group's output columns through that alias.
- Fixed derived table output exposure so qualified inner expression names such as `x1.d` are exposed to the outer query as the derived table alias, e.g. `x2.d`.

Focused PHP coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectDDerivedAggregateDynamicTest.php`.
- 1,001 distinct TestRunner PASS cases.
- 7,002 focused behavior assertions.
- Dynamic generic application row sets vary left rows, inner duplicate counts, grouped `count(*)`, grouped `min()`, null-extension rows, and qualified output column names across 1,000 seeds.

Non-overlap:

- This owns the residual `selectD-4.1` parenthesized join alias plus derived aggregate LEFT JOIN behavior.
- It does not repeat prior `selectD` parenthesized FROM/JOIN batches, `selectC` alias resolution, `selectH` omit-unused batches, grouped SELECT text, expression ORDER BY, JSON table cursor/source/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `selectD.test` is already part of the hydrated upstream manifest coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDDerivedAggregateDynamicTest.php`
  - Result: `1 test files, 7002 assertions, 0 failures`
  - PASS lines: `1001`

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql`, `SQLiteSelectQuery`, parenthesized join, derived table, aggregate, `GROUP BY`, and LEFT JOIN execution.
