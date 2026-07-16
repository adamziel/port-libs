# Real Upstream Corpus SELECT Core Dynamic

- Session: `port-dev-sqlite-yield-dyn-real-select-20260530T171939Z`
- Base accepted HEAD: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`
- Micro-slice: `real-upstream-corpus-select-core-dynamic-20260530T171939Z-0`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
  - Focused upstream scenarios: `select6-1.2` through `select6-1.9`, `select6-2.1`, `select6-3.1`, `select6-4.1`, and adjacent derived-table `ORDER BY`/`LIMIT` behavior from the same FROM-subquery corpus.

## Delta

Added `SQLiteRealUpstreamCorpusSelectCoreDynamicTest.php` with 531 focused PHP assertions over generic `items` and `copy_items` rows. The cases exercise `SQLiteSelectSql` behavior for derived-table SELECT sources, nested derived SELECTs, `DISTINCT`, grouped derived rows, joined grouped subqueries, alias-qualified projection, derived `WHERE` filters, `ORDER BY`, `LIMIT`, and `OFFSET`.

This is non-overlapping with accepted grouped SELECT text, single-table/JOIN text dispatch, expression `ORDER BY`, JSON table source/cursor/constraint work, and storage/VFS/B-tree batches. It uses generic application table/column names only and does not add domain-shaped APIs or fixtures.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicTest.php`
  - Result: `1 test files, 531 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded `SQLiteSelectSql` row-array executor and the hydrated upstream SQLite test corpus as source truth.
