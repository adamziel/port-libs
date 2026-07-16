# real-upstream-corpus-select-core-dynamic-20260531T062313Z-0

Implemented a focused real-upstream SELECT core corpus slice from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- Upstream scenarios: `select1-6.1` through `select1-6.9.16`

The PHP coverage is in `SQLiteRealUpstreamSelectCoreDynamicColumnNames20260531Test.php`.
It dynamically ports the upstream `execsql2` result-shape behavior for aliases,
expression result names, qualified source columns, table aliases, joined `*`
projection names, and `DISTINCT *` source names.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicColumnNames20260531Test.php`
- Result: `1 test files, 6258 assertions, 0 failures`
- PASS cases: 1252

Non-overlap:

- Avoids accepted SELECT expression `ORDER BY`, `GROUP BY`/`HAVING` SQL text,
  subquery, JSON table source/cursor/constraint, B-tree, VFS, WAL, and
  source-neutral cleanup clusters.
- Owns only `select1.test` result-column naming and joined-source result shape.

Dependency closure:

- No new support component needed; the existing bounded `SQLiteSelectSql`
  executor and result row shape are reused.
