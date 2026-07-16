# Create Index Expression Collation Corpus Next7

This slice adds parser support and focused corpus coverage for SQLite
expression-index terms wrapped in redundant parentheses, including `COLLATE`
and `ASC`/`DESC` modifiers placed either inside the expression wrapper or after
it.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCreateIndexExpressionCollationCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
30 PASS lines
1 test files, 94 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-create-index-expression-collation-preflight.php
```

Status delta:

- `phpPass`: `2017 -> 2047` from the 30 newly verified PASS lines above.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP corpus growth
  for already mapped SQLite `CREATE INDEX` expression/collation behavior.
- Dependency closure: no new support component needed; this reuses the existing
  native SQL schema parser and JSON path validation helpers.

Non-overlap:

- Avoids accepted expression-index range-cost ranking, expression `ORDER BY`,
  JSON hidden/visible constraint pushdown, Unicode GLOB ranges, B-tree overflow
  freelist release, rollback/VFS apply clusters, and batch5a collation index
  range implication. This slice is limited to `CREATE INDEX` expression-term
  parsing for parenthesized expression/collation syntax.
