# real-upstream-corpus-select-core-dynamic-select2-select5-batch-20260531

Base accepted HEAD: `b596d6a43afd4ccaf50904f879de33fed9b5b7f3`.

This slice adds a lane-local PHP TestRunner corpus batch for real upstream
SQLite SELECT-core behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`

Covered upstream scenarios:

- `select2-1.1` / `select2-1.2`: nested SELECT execution while scanning an
  outer `SELECT DISTINCT` result.
- `select2-3.1` / `select2-3.2`: commuted equality/range predicate forms over
  the large `tbl2` shape.
- `select2-4.6` / `select2-4.7`: `CASE` truthiness in joined WHERE clauses.
- `select5-5.11`: `GROUP BY` expression rendering and aggregate expression
  ordering.
- `select5-8.1` / `select5-8.6`: join aggregate grouping and aggregate
  `ORDER BY` behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicSelect2Select5BatchTest.php`
  - `1 test files, 7006 assertions, 0 failures`
  - 1001 distinct TestRunner PASS cases

Non-overlap:

- Avoids accepted JSON table, WAL/VFS, B-tree, source-neutral, SELECT
  expression `ORDER BY`, grouped SELECT text, subquery text, comma-LIMIT, and
  prior SELECT core range-matrix/thousand batches.
- Adds no production API, no metadata-only admission records, no fabricated
  upstream script IDs, and no WordPress-specific libsqlite names.

Dependency closure:

- No new support component is needed. The batch reuses the existing
  `SQLiteSelectSql` row-array executor and TestRunner harness.
