# SQLite current next44: SELECT VALUES source column aliases

## Behavior

- Added parser-level support for SQLite `FROM (VALUES (...)) AS alias(col, ...)` source column lists in `SQLiteSelectSql`.
- Renames inline VALUES source columns before row production, so copied WordPress import staging tuples can use semantic names such as `name`, `value`, and `autoload` instead of `column1`, `column2`, and `column3`.
- Preserves existing `(VALUES (...)) AS alias` behavior and validates malformed alias tails, empty column lists, invalid column names, and row-width mismatches.

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlValuesAliasCurrentNext44Test.php
Focused test run: 1 selected test files (root lock skipped)
54 PASS lines
1 test files, 54 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/wordpress-select-sql-values-alias.php
candidateNames: blogdescription, blogname, siteurl
```

## Status delta

- `lane-status.json` `phpPass`: `15880 -> 15934` (`+54` focused PASS cases).
- `phpFail`: `0`.
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit was mapped in this slice.

## Non-overlap

This slice avoids accepted VFS sync/apply, process locks, rollback commit, super-journal, WAL checkpoint/savepoint byte truncation, B-tree root collapse/page move/overflow freelist, JSON table cursor/source/hidden/visible constraints, Unicode GLOB, SELECT subquery execution, comma LIMIT, grouped SELECT SQL text, expression ORDER BY, and derived-table materialization. It targets the narrower unhandled SQLite VALUES source alias-list syntax used by inline WordPress staging SQL.

## Dependency closure

No new support component is needed. The implementation reuses the existing `SQLiteSelectSql` parser, `executeValuesClause()`, and row-array executor.

## Next task

Broaden parser/executor support for VALUES sources in JOIN contexts if needed by later WordPress import SQL; this patch intentionally keeps join-source behavior out of scope because the current accepted join pipeline has separate constraints.
