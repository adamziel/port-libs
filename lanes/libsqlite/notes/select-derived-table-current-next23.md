# SELECT derived table current next23

Implemented bounded parser-level derived-table materialization for `SELECT ... FROM (SELECT ...) AS alias` in `SQLiteSelectSql`.

Focused behavior:

- Materializes derived-table rows from inner `SELECT` text before outer predicates, ordering, grouping, limits, joins, and projection.
- Supports inner CTEs, compound SELECT arms, DISTINCT/ALL, GROUP BY/HAVING, wildcard projection, JSON scalar dispatch, and nested derived tables.
- Preserves Application import staging behavior where copied `wp_options` rows are filtered in a derived table and joined to import metadata without requiring `ext/sqlite`.
- Accepts omitted aliases for unqualified outer references, rejects malformed aliases, and propagates inner/outer SELECT errors.

Evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectDerivedTableCurrentNext23Test.php`
- Result: `1 test files, 57 assertions, 0 failures`
- New focused PASS lines: `57`

Dashboard delta:

- `phpPass`: `8166` -> `8223`
- `phpFail`: remains `0`
- `benchmarkDenominator.mapped`: unchanged; this is a focused PHP behavior slice, not a new upstream inventory unit.

Non-overlap:

- Avoids batch21 SELECT window FILTER, nested JSON LEFT JOIN rowid aliases, PRAGMA table-valued rows, recursive trigger savepoint planning, B-tree/WAL/VDBE clusters, expression CASE collation, ATTACH trigger FK resolution, release countability, and Application import transaction planning.
- Avoids accepted parser-level single-table/JOIN/GROUP BY SQL text, expression ORDER BY, correlated subqueries, JSON table SELECT sources/hidden/visible constraints, WAL/VFS/B-tree apply clusters, and Unicode GLOB behavior.

Dependency closure:

- No new support component needed. The slice reuses the existing `SQLiteSelectSql` parser/executor, `SQLiteSelectQuery`, and native row-array table sources.
