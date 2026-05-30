# real-upstream-corpus-select-core-dynamic-20260530T211510Z-0

Added `SQLiteRealUpstreamSelectDParenthesizedJoinDynamicTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- `selectD-1.1`: parenthesized comma `FROM` name resolution.
- `selectD-1.2.1` through `selectD-1.2.3`: nested parenthesized JOIN groups and qualified star projection.
- `selectD-1.2.6`: missing auxiliary schema rejection.
- `selectD-1.2.7`: schema table aliases inside nested JOIN groups.
- `selectD-1.7`: LEFT JOIN parenthesized group null-extension with qualified projection.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDParenthesizedJoinDynamicTest.php`
- Result: `1 test files, 4038 assertions, 0 failures`
- Selected PASS cases: 1009 new focused TestRunner cases.

Non-overlap:

- This slice owns the residual `selectD.test` parenthesized FROM/JOIN name-resolution cluster.
- It does not repeat accepted single-table/JOIN SELECT text dispatch, grouped SELECT text, expression `ORDER BY`, `selectC.test` alias-resolution coverage, JSON table source/cursor/constraint work, B-tree/VFS/WAL storage clusters, or metadata-only runner rows.
- Mapped denominator remains unchanged because mapped inventory is already complete at `1589 / 1589`.

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql`, parenthesized table/join parsing, JOIN `ON`/`USING` execution, schema-qualified table resolution, and SELECT projection behavior.
