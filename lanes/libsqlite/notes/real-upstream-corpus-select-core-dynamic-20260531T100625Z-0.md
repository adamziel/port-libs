# real-upstream-corpus-select-core-dynamic-20260531T100625Z-0

Base accepted HEAD: `db6e720333280b900b4f227c59e0153ddd55f2fc`

Implemented one real upstream SELECT result-row behavior cluster from hydrated
SQLite source truth:

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Upstream sections cited: `e_select-4.7` and row-count requirement `R-64138-28774`.
- Ported behavior: aggregate SELECT without `GROUP BY` over an empty filtered
  input returns exactly one row, and non-aggregate result expressions are
  evaluated against a NULL source row.

Behavior fix:

- `SQLiteSelectSql` now treats aggregate functions nested inside result
  predicates as aggregate result expressions, so shapes like
  `max(setting_id) IS NULL` enter the implicit aggregate path.
- `SQLiteSelectQuery` now materializes missing non-aggregate source columns as
  `NULL` for the empty-input implicit aggregate summary row before projection.

Focused coverage:

- Added `SQLiteRealUpstreamESelectEmptyAggregateDynamic20260531T100625ZTest.php`
  with 1 upstream source-citation case, 1000 dynamic behavior cases, and 1
  dependency/non-overlap note.
- Each dynamic case runs six SELECTs over generic application rows covering
  `count(*)`, `count(column)`, `max(column)`, `total(column)`, `avg(column)`,
  nested/compound aggregate predicates, non-aggregate column projection, and
  non-aggregate expression projection after the WHERE clause removes every row.

Verification:

- Initial probe before the fix exposed `SQLite SELECT expression row is missing
  column one` for `SELECT one,two,count(*) FROM a1 WHERE 0`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectEmptyAggregateDynamic20260531T100625ZTest.php`
- Result: `1 test files, 36008 assertions, 0 failures` and 1002 PASS lines.

Expected dashboard movement:

- `phpPass`: `2856519 -> 2857521` (`+1002` selected focused PASS lines).
- Mapped coverage remains `1589 / 1589`; this is behavior-backed PASS growth
  over already mapped upstream SELECT inventory.

Non-overlap:

- This owns only empty-input implicit aggregate result-row semantics from
  `e_select-4.7` and `R-64138-28774`.
- It avoids grouped SELECT text, SELECT joins/subqueries/order-expression,
  JSON table, B-tree, WAL, VFS, PRAGMA, window, and recent e_select join/WHERE
  dynamic batches.

Dependency closure:

- No new support component is needed. The patch reuses the existing native PHP
  SELECT executor, grouped aggregate summarizer, projection evaluator, and
  predicate parser.
- Remaining follow-up for a larger upstream section: wildcard projection in
  `SELECT aggregate(...), *` over an empty implicit aggregate source still needs
  a separate output-column shape fix before admitting `e_select-4.7.2`.
