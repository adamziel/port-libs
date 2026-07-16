# SELECT no-FROM corpus next9

Slice: `yield-sqlite-select-planner-high-yield-upstream-next9`

Behavior implemented:

- `SQLiteSelectSql` now accepts bounded SQLite `SELECT` statements without a `FROM` clause by planning the SQLite implicit single input row.
- Covered constant projection, scalar/JSON functions, `WHERE`, `ORDER BY`, `LIMIT`/`OFFSET`, comma `LIMIT`, `DISTINCT`/`ALL`, bind parameters, and CTE-backed scalar/`EXISTS`/`IN` predicates.
- Guarded unsupported no-source wildcard projection and no-source `GROUP BY`/`HAVING` paths.

Verified focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectNoFromCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
57 PASS lines
1 test files, 57 assertions, 0 failures
```

Status delta:

- `phpPass`: `2311` -> `2368` (`+57`, exact verified PASS-line delta).
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit is claimed.

Application smoke:

```text
php lanes/libsqlite/examples/application-select-no-from-probe.php
```

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP `SQLiteSelectSql`, scalar, JSON, predicate, projection, and result helpers.

Non-overlap:

- Avoids accepted JOIN text dispatch, single-table SELECT SQL, GROUP BY/HAVING text, expression `ORDER BY`, correlated subquery text over table sources, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, and batch5b corpus blocks.
