# real-upstream-corpus-select-core-dynamic-20260531T044755Z-0

Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`.

Added `SQLiteRealUpstreamSelectCoreDynamicWhereTruthiness20260531Test.php`,
which ports real upstream SQLite `test/e_select.test` section
`e_select-3.1.1` through `e_select-3.1.6`.

The batch owns dynamic select-core `WHERE` truthiness behavior only:
numeric column truth, text-to-numeric truth, numeric `z` truth, string
concatenation truth, `IS NULL`, and arithmetic truth from `z - 78.43`.
It avoids accepted SELECT projection/JOIN/GROUP BY/subquery/expression
`ORDER BY`, `selectD` parenthesized join, and JSON table SELECT-source
clusters.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicWhereTruthiness20260531Test.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicWhereTruthiness20260531Test.php`
  passed: `1 test files, 4327 assertions, 0 failures`.
- PASS-line growth: `1082` focused TestRunner PASS cases.

Dependency closure: no new support component is needed; this reuses
lane-local `SQLiteSelectSql` predicate, expression, ORDER BY, and row-array
execution.
