# real-upstream-corpus-select-core-dynamic-20260530T233637Z-0

Base accepted HEAD: `d7c5d7f50d0d0c3f24c91125036d23912559b628`.

Ported real upstream SQLite SELECT core behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test`
- `selectG-110` and `selectG-120`: a multi-row `VALUES` clause in a scalar expression returns the first row only.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- `selectH-4.1` and `selectH-4.2`: compound `DISTINCT ... UNION ALL` rows preserve the left arm when the right arm is empty, directly and under an outer SELECT.
- `selectH-5.1` and `selectH-5.2`: compound `DISTINCT ... UNION ALL` rows preserve left rows and aggregate counts when the right arm is empty.

Implementation movement:

- Fixed `SQLiteSelectSql` so parenthesized scalar `VALUES(...)` expressions are parsed as scalar subqueries, including the compact SQLite spelling without whitespace after `VALUES`.
- Extended top-level `VALUES(...)` recognition to accept the compact form already used by upstream `selectG.test`.

Focused PHP coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectGValuesSelectHCompoundDynamicTest.php`.
- 1,004 distinct TestRunner PASS cases.
- 17,022 focused behavior assertions.
- Dynamic generic application rows vary scalar VALUES first/unused rows, duplicate left-arm values, empty right-arm tables, schema names, direct compound output, derived compound output, and aggregate counts across 1,000 seeds.

Non-overlap:

- This owns the residual `selectG` scalar multi-row `VALUES` expression behavior and `selectH` empty-right compound DISTINCT/UNION ALL preservation behavior.
- It does not repeat prior `selectC` alias-resolution batches, `selectD-4.1` derived aggregate parenthesized join behavior, `select8` LIMIT/OFFSET coverage, grouped SELECT text, expression ORDER BY, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `selectG.test` and `selectH.test` are already present in the hydrated upstream manifest coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectGValuesSelectHCompoundDynamicTest.php`
  - Result: `1 test files, 17022 assertions, 0 failures`
  - PASS lines: `1004`

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql`, `SQLiteSelectQuery`, scalar subquery, `VALUES`, compound SELECT, DISTINCT, derived-table, and aggregate execution.
