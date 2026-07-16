# ORDER BY Collation And NULL Placement Slice

Date: 2026-05-27

Scope:
- Adds shared `SQLiteSelectResult` ordering support for `COLLATE BINARY`,
  `COLLATE NOCASE`, `COLLATE RTRIM`, and explicit `NULLS FIRST` / `NULLS LAST`.
- Wires parser-level `SQLiteSelectSql` `ORDER BY` terms to preserve collation
  and NULL-placement metadata for direct columns, hidden expression order
  columns, aliases, grouped SELECT output, and compound SELECT tails.
- Adds a Application-shaped smoke for deterministic copied `wp_options` ordering
  without the SQLite extension.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteOrderByCollateNullsCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
31 PASS lines
1 test files, 34 assertions, 0 failures
```

Regression/evidence commands:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteOrderByCollateNullsCorpusTest.php lanes/libsqlite/tests/SQLiteSelectDistinctSqlTest.php lanes/libsqlite/tests/SQLiteUpstreamCorpusTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 227 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteSelectResult.php
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteOrderByCollateNullsCorpusTest.php
php -l lanes/libsqlite/examples/application-select-order-collate-nulls.php
No syntax errors detected in all changed PHP files.

php lanes/libsqlite/examples/application-select-order-collate-nulls.php
orderedOptionIds: [2, 1, 4, 3, 5, 6]

git diff --check -- lanes/libsqlite
passed with no output
```

Dashboard delta:
- `phpPass`: `2311 -> 2342` from the verified +31 focused PASS-line delta.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior
  coverage rather than a newly mapped upstream manifest unit.

Dependency closure:
- No new support component is needed. The slice reuses lane-local SELECT SQL
  parsing, result ordering, ASCII NOCASE collation, RTRIM collation, and pure
  PHP row arrays.

Non-overlap:
- Avoids accepted SQL expression `ORDER BY`, comma `LIMIT`, grouped SELECT
  text, SELECT subqueries, Unicode GLOB ranges, VFS writer/lock/sync clusters,
  WAL byte truncation, rollback-journal apply/commit, JSON table source/cursor
  work, B-tree page moves/root collapse/overflow release, and batch5b corpus
  surfaces. This slice is specifically per-term ORDER BY collation and explicit
  NULL placement in the current SELECT result/parser boundary.
