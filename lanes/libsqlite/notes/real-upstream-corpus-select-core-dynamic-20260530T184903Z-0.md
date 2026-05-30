# real-upstream-corpus-select-core-dynamic-20260530T184903Z-0

Added `SQLiteRealUpstreamSelectBDerivedCompoundDynamicTest.php` as an additive
real upstream SELECT corpus slice.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`
- Upstream scenarios: `selectB-1.1`, `selectB-2.2` through `selectB-2.12`,
  and the two-column derived compound family documented around `selectB-2.15`.

Focused PHP coverage:

- 1,501 distinct TestRunner PASS cases.
- 9,004 focused assertions.
- Dynamic derived `UNION ALL` subqueries with pushed outer `WHERE` predicates,
  `ORDER BY`, `LIMIT`, and `OFFSET`.
- Dynamic flattened-equivalent compounds that preserve the upstream
  transformation contract from `selectB.test`.
- Dynamic three-arm compound row ordering and filtered LIMIT/OFFSET windows.
- Dynamic two-column compound derived sources and flattened equivalents.

Non-overlap:

- This does not repeat existing `select1` through `select9`/`selectA` corpus
  files, accepted SELECT SQL text/JOIN/GROUP/subquery/order-expression/comma
  LIMIT clusters, JSON table cursor/source/constraint work, VFS/WAL/B-tree
  storage clusters, or metadata-only upstream admission rows.
- The new surface is `selectB.test` derived compound SELECT flattening parity
  over real upstream-shaped table rows, extended dynamically across thresholds
  and result windows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectBDerivedCompoundDynamicTest.php`
  passed: `1 test files, 9004 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The slice reuses the native
  `SQLiteSelectSql` parser/executor and existing compound SELECT, derived
  source, predicate, ordering, and LIMIT/OFFSET behavior.
