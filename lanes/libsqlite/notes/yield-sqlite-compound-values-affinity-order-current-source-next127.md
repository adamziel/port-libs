# SQLite compound VALUES affinity/order current-source next127

- Implemented parser-level top-level `VALUES` support in `SQLiteSelectSql`, including `VALUES` as the left arm of compound SELECT text.
- Preserves SQLite compound behavior for left-arm output names (`column1`, `column2`), right-arm positional renaming, `UNION`/`UNION ALL`/`INTERSECT`/`EXCEPT`, storage-class ordering, `COLLATE`, `NULLS FIRST/LAST`, `LIMIT`/`OFFSET`, and CTE-fed VALUES compounds.
- Rejects `ORDER BY`/`LIMIT` after a final `VALUES` arm to match SQLite syntax.
- Application smoke: `examples/application-compound-values-affinity-order-current-source-next127.php` previews copied `wp_options` imports seeded by staged VALUES rows, preserving affinity/order behavior without ext/sqlite.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteCompoundValuesAffinityOrderCurrentSourceNext127Test.php
php -l lanes/libsqlite/examples/application-compound-values-affinity-order-current-source-next127.php
No syntax errors detected in all changed PHP files.

php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundValuesAffinityOrderCurrentSourceNext127Test.php
1 test files, 62 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundValuesAffinityOrderCurrentSourceNext127Test.php lanes/libsqlite/tests/SQLiteCompoundValuesNameResolutionCurrentSourceNext123Test.php lanes/libsqlite/tests/SQLiteCompoundSelectOrderLimitCurrentSourceNext110Test.php lanes/libsqlite/tests/SQLiteCompoundExceptIntersectAffinityTest.php lanes/libsqlite/tests/SQLiteSelectValuesSourceTest.php
5 test files, 251 assertions, 0 failures

php lanes/libsqlite/examples/application-compound-values-affinity-order-current-source-next127.php
orderedPriorities: [40, 50, "40"]
outputColumns: ["column1", "column2"]
```

Dashboard delta:

- Adds 62 focused PASS assertions in a new lane-scoped test file.
- Expected `phpPass`: `50809 -> 50871`; mapped upstream denominator unchanged.

Non-overlap:

- Avoids accepted compound VALUES name-resolution next123 and compound ORDER-expression next110 by adding the missing top-level `VALUES` compound arm executor and final-VALUES syntax guard.
- Does not touch accepted JSON, WAL, B-tree, VFS, encoding, trigger, PRAGMA, or suite evidence surfaces.

Dependency closure:

- No new support component is needed; this reuses the existing bounded `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectCompound`, and `SQLiteSelectResult` components.
