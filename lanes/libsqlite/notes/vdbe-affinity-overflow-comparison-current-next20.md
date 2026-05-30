### 2026-05-27 VDBE affinity overflow comparison current next20

Micro-slice: `yield-sqlite-vdbe-type-affinity-comparison-current-next20`.

This slice fixes a VDBE-style numeric-affinity comparison edge where PHP's
overflowing string-to-int cast could collapse text/blob numeric literals above
`9223372036854775807` onto `PHP_INT_MAX`. SQLite converts those out-of-range
integer-looking values to REAL before comparison, so they sort above the max
integer instead of equal to it.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeAffinityOverflowComparisonNext20Test.php
Focused test run: 1 selected test files (root lock skipped)
41 PASS lines
1 test files, 55 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-vdbe-affinity-overflow-comparison.php --self-test
OK application vdbe affinity overflow comparison smoke
```

Status delta:

- `phpPass`: `6957 -> 6998` from the 41 new focused PASS lines above.
- `benchmarkDenominator.mapped`: unchanged at `456 / 1589`; no new upstream
  inventory unit was mapped.

Dependency closure:

No new support component is needed. This reuses the existing native
`SQLiteAffinityComparison`, `SQLiteVdbeSortCompare`, and `SQLiteBlobValue`
components.

Non-overlap:

This avoids accepted Unicode GLOB ranges, SELECT SQL subqueries/comma LIMIT,
VFS sync/rollback/super-journal/file-lock/write clusters, B-tree root collapse
and overflow freelist release, JSON table source/cursor/visible/hidden
constraints, SQL expression ORDER BY/range-cost planning, and earlier ordinary
affinity comparison cases. The new behavior is specifically the SQLite
out-of-range integer literal conversion rule inside VDBE comparison affinity.
