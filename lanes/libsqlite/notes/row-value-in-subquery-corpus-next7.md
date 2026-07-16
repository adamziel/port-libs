# Row-Value IN Subquery Corpus Next7

Slice: `yield-sqlite-in-operator-row-value-subquery-corpus-next7`

Behavior added:

- `SQLiteSelectSql` now allows row-value left operands for `IN` / `NOT IN` subqueries that return multiple columns.
- `SQLiteSelectPredicate` now compares tuple candidates through the existing row-value comparison path, including SQL NULL/unknown propagation.
- Scalar `IN` subqueries still reject multi-column result rows, and tuple width mismatches still fail through the row-value comparison guard.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueInSubqueryCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
50 PASS lines
1 test files, 51 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `2017 -> 2067` (`+50` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is PHP corpus coverage, not a newly mapped upstream inventory unit.

Application smoke:

```text
php lanes/libsqlite/examples/application-row-value-in-subquery.php
```

Dependency closure:

- No new support component needed. This reuses the existing bounded `SQLiteSelectSql`, `SQLiteSelectPredicate`, and row-value comparison primitives.

Non-overlap:

- Avoids accepted row-value comparison-only coverage, accepted scalar `IN`/`EXISTS` subquery filters, parser-level SELECT/JOIN/GROUP BY text, expression `ORDER BY`, JSON table source/cursor/constraints, VFS/WAL/B-tree clusters, and newer accepted storage work.
- Remaining follow-up outside this slice: multi-source correlated subqueries and aggregate `GROUP BY` subquery forms are still separate executor gaps.
