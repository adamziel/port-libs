# Real Upstream Select Core Dynamic Case-Insensitive Columns

Base accepted HEAD: `eb22516d8f29af7145a28b1cc2453b19311c1d0b`.

This slice ports upstream `select1.test` column-expression behavior around `select1-6.4`, `select1-6.5`, `select1-6.6`, and `select1-6.9.1` into focused PHP coverage for `SQLiteSelectSql`.

Behavior fixed:

- `SELECT f1+F2 AS xyzzy FROM test1 ORDER BY f2` now resolves `F2` case-insensitively like SQLite identifiers.
- Qualified mixed-case references such as `TEST1.F1`, `test1.F2`, and `TeSt1.F2` resolve against row-array source columns.
- Ambiguity detection remains intact for multiple case-insensitive matches.

Focused coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreCaseInsensitiveDynamicTest.php`
- 1 upstream source-citation case.
- 1,250 dynamic `select1-6.4` mixed-case expression/order cases.
- 250 dynamic `select1-6.6` joined mixed-case expression cases.
- Focused result: `1 test files, 6008 assertions, 0 failures` with `1501` PASS lines.

Non-overlap:

This does not repeat accepted flattened SELECT WHERE/ORDER/GROUP BY, expression `ORDER BY`, grouped SELECT text, subquery, JSON table, VFS, WAL, B-tree, or source-neutral cleanup slices. Existing SELECT core tests asserted flattened values for many cases; this slice fixes and verifies identifier case-folding inside expression column lookup from the real upstream `select1.test` column-expression cluster.

Dependency closure:

No new support component is required. The slice reuses existing bounded `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteSelectExpression` PHP execution components.
