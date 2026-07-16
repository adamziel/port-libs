# SELECT Subquery Join Corpus

Slice: `yield-sqlite-select-subquery-join-corpus-next`

Added `SQLiteSelectSubqueryJoinCorpusTest.php` with 53 independent focused PASS
cases over parser-level `SQLiteSelectSql` execution where correlated `EXISTS`,
`NOT EXISTS`, `IN`, `NOT IN`, and scalar SELECT subqueries are evaluated against
INNER, LEFT, and CROSS joined `wp_options`-style row sources.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSubqueryJoinCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 53 assertions, 0 failures
```

Status delta:

- `phpPass`: `933 -> 986` (`+53` verified PASS lines from the focused test).
- `benchmarkDenominator.mapped`: unchanged; this is upstream-style PHP corpus
  coverage, not a newly mapped upstream inventory unit.

Application smoke:

```text
php lanes/libsqlite/examples/application-select-subquery-join-corpus.php
```

The smoke reports copied `wp_options` rows joined to site visibility rows and
filtered/projected through correlated SELECT subqueries without requiring
`ext/sqlite`.

Non-overlap:

This does not repeat the accepted scalar subquery-only, JOIN text dispatch,
JSON table source/cursor/constraint, expression ORDER BY, grouped SELECT,
VFS/WAL/B-tree, or Unicode GLOB clusters. It is a focused corpus addition for
the combined SELECT subquery plus joined-source behavior surface.

Dependency closure:

No new support component is needed. The slice reuses the existing native PHP
`SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectPredicate`, and
`SQLiteSelectExpression` components.
