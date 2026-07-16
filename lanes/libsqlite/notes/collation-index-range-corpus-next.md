# Collation Index Range Corpus Next

This slice adds collation-aware partial-index implication for `SQLiteIndexPredicate`.
Point, range, `BETWEEN`, and `IN` proofs now compare string literals under the
index term's built-in `BINARY`, `NOCASE`, or `RTRIM` collation instead of always
using raw binary string comparison.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCollationIndexRangeCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 25 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `1336 -> 1361` (`+25` focused PASS lines verified locally).
- `benchmarkDenominator.mapped`: unchanged; this is a focused PHP behavior
  corpus, not a newly mapped upstream inventory unit.

Non-overlap:

- Avoids accepted Unicode GLOB range matching, expression-index range-cost
  ranking, SELECT SQL expression `ORDER BY`, JSON table constraints/cursors,
  VFS writer/lock/sync work, WAL rollback/savepoint/checkpoint slices, B-tree
  page move/root-collapse/overflow release clusters, and batch3 SQL/JSON/DML/WAL
  corpus blocks.

Dependency closure:

- No new support component is needed. The patch reuses existing native
  `SQLiteIndexPredicate`, `SQLiteCreateIndex`, and `SQLiteDatabase` index
  metadata paths.

Root harness:

- Not run - isolated micro-slice.
