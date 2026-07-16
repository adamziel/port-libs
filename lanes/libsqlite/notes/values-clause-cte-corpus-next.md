# VALUES Clause CTE Corpus

Date: 2026-05-27

Slice: `yield-sqlite-values-clause-cte-corpus-next`

## Behavior

This slice adds bounded native PHP materialization for SQLite `WITH` common
table expressions whose body is a `VALUES` clause. The materialized rows use
SQLite's default `column1`, `column2`, ... names before optional CTE column-list
renaming, then flow through the existing `SQLiteSelectSql` executor.

The focused corpus covers default and explicit CTE column names, literal storage
classes, predicates, `IN`/`EXISTS` subqueries, joins, grouping/HAVING, compound
SELECT arms, positional/named bind parameters, scalar/JSON/BLOB expressions,
NULL behavior, and malformed VALUES guards.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteValuesClauseCteCorpusTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 54 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-select-sql-values-cte.php
```

Result: emitted copied `wp_options` staging rows matched through a
VALUES-backed CTE and `LEFT JOIN`, including a missing staged option ID.

## Counters

- `phpPass`: `1336 -> 1390` (`+54`, from the focused PASS-line/assertion delta)
- `benchmarkDenominator.mapped`: `451 -> 452` for one newly mapped focused
  upstream inventory row: VALUES-backed CTE SELECT execution.

## Non-Overlap

This does not repeat accepted SELECT SQL CTEs backed by SELECT bodies,
parser-level JSON table sources, grouped SELECT SQL text, SELECT JOIN text,
subqueries, expression `ORDER BY`, comma `LIMIT`, or scalar-operator slices. It
narrows the previously unhandled upstream behavior to `WITH ... AS (VALUES
...)` materialization.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded PHP
`SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectExpression`,
`SQLiteSelectPredicate`, aggregate, JSON, and compound SELECT components.
