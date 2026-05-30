# Subquery Flattening EXISTS Corpus Next8

Slice: `yield-sqlite-subquery-flattening-exists-corpus-next8`

Added `SQLiteSubqueryFlatteningExistsCorpusTest.php` with 53 independent focused
PASS cases for correlated `EXISTS` / `NOT EXISTS` and `IN` / `NOT IN`
subqueries whose inner SELECT uses `JOIN`, `LEFT JOIN`, or `CROSS JOIN`
metadata sources. The implementation removes the earlier one-source guard from
`SQLiteSelectSql::correlatedSubqueryRows()` so joined subquery sources execute
against each outer row instead of being rejected before the predicate can be
evaluated.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSubqueryFlatteningExistsCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 53 assertions, 0 failures
```

PASS delta: `+53` verified PASS lines. `lane-status.json` `phpPass` moves from
`2311` to `2364`. `benchmarkDenominator.mapped` is unchanged because this adds
focused PHP corpus coverage for an already mapped upstream SELECT/subquery
surface rather than a new hydrated upstream inventory unit.

Application smoke:

```text
php lanes/libsqlite/examples/application-select-subquery-flattening-exists.php
```

Non-overlap: this does not repeat the accepted parser-level correlated
`EXISTS`/`IN` single-source subquery text slice, scalar subquery expression
slice, SELECT JOIN text slice, GROUP BY text slice, expression `ORDER BY`, JSON
table source/cursor/constraint work, VFS/WAL/B-tree storage clusters, Unicode
GLOB, or batch5b corpus blocks. It specifically covers the formerly rejected
joined-source inner subquery path, which is flattening-sensitive because the
inner join must be evaluated per outer row without multiplying or filtering the
outer query directly.

Dependency closure: no new support component is needed; this reuses the
existing native PHP SELECT SQL planner/executor, join executor, and predicate
evaluator.

Next task: extend correlated subqueries to grouped aggregate inner SELECTs or
compound-arm inner SELECTs after choosing a non-overlapping current-base slice
with comparable focused PASS growth.
