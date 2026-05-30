# real-upstream-corpus-select-core-dynamic-20260530T215354Z-0

Base accepted HEAD: `e2fccb0f3569072f6fcb2b28f92689aa5a125f9e`.

Implemented one real upstream SELECT corpus batch from hydrated SQLite source:

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- Upstream section: `select1-17.3`
- Behavior: derived compound subquery row production with `UNION ALL`, `ORDER BY y,z`, `LIMIT`, and dynamic `OFFSET`, joined to an outer table.
- PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamSelect1DerivedCompoundDynamicTest.php`
- Focused result: `1 test files, 16132 assertions, 0 failures`
- Distinct TestRunner PASS cases: `2017`

Non-overlap:

- Avoids already accepted `select1-17.1` and `select1-17.2` derived `ORDER BY` / `LIMIT` coverage in `SQLiteRealUpstreamSelect1DerivedOrderLimitDynamicTest.php`.
- Avoids accepted single-table/JOIN SELECT SQL text, grouped SELECT SQL text, expression `ORDER BY`, wildcard, subquery filter, compound top-level SELECT, and JSON table SELECT-source clusters.
- This batch owns the previously separate `select1-17.3` compound derived subquery shape.

Dependency closure:

- No new support component is needed. The batch reuses `SQLiteSelectSql` compound subquery planning and row-array execution.
- A red probe found later `select1-18.*` correlated subquery/NULL `BETWEEN` behavior is not ready for a high-yield batch yet; that should be a follow-up behavior-fix slice before admitting the next correlated SELECT corpus.
