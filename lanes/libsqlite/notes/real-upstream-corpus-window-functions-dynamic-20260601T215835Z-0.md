# Real Upstream Window6 Custom Collation Dynamic Batch

- Slice: `real-upstream-corpus-window-functions-dynamic-20260601T215835Z-0`
- Base accepted HEAD: `c1c285e2cc3e5a7187d4f4af21088cdee98a4ccf`
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
- Ported upstream sections:
  - `window6.test` `3.0`: `CREATE TABLE window(x COLLATE window)` plus `ORDER BY x COLLATE window` returns `cate bob alice` with `db collate window wincmp`.
  - `window6.test` `3.1`: `CREATE INDEX window ON x1(x COLLATE window)` preserves the same custom-collation order through the indexed path.

Implementation:

- `SQLiteSelectResult::orderBy()` now accepts an optional map of custom text collations and applies those callbacks after SQLite storage-class ordering, preserving existing built-in `BINARY`, `NOCASE`, `RTRIM`, and `REVERSE` behavior.
- `SQLiteRealUpstreamWindow6CustomCollationDynamic20260601Test.php` adds 1000 dynamic cases plus exact-source, fixed section, error, and dependency-closure tests. The dynamic cases exercise both the table-order path through `SQLiteSelectResult::orderBy()` and the indexed path through `SQLiteExpressionIndexCollationCursor`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteSelectResult.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectResult.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow6CustomCollationDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow6CustomCollationDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow6CustomCollationDynamic20260601Test.php`
  - `1 test files, 3013 assertions, 0 failures`
  - Adds `+1006` focused TestRunner PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCollationIndexExpressionCurrentNext56Test.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php`
  - `2 test files, 3055 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupResultDynamic20260531T162339ZTest.php`
  - `1 test files, 42765 assertions, 0 failures`

Non-overlap:

- This owns only `window6.test` `3.0` and `3.1` custom collation ordering/index behavior.
- It avoids accepted window6 keyword/value/frame/nth/recursive coverage (`1.*`, `5.*`, `8.*`, `9.*`, `10.*`, `11.*`), `windowE` custom RANGE frames, grouped SELECT text, expression ORDER BY, JSON table, WAL/VFS/B-tree, source-neutral cleanup, and metadata-only runner rows.

Dependency closure:

- No new support component is needed. The patch reuses the existing SELECT result sorter and expression-index cursor, adding a generic optional custom-collation callback map to `SQLiteSelectResult::orderBy()`.

Follow-up:

- A future parser/executor slice can thread custom collation registration through full SQL text execution if a broader upstream section requires SQL-level `db collate` registration. This batch intentionally keeps the behavior bounded to the existing result/index execution helpers.
