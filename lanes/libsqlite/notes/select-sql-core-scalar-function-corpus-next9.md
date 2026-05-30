# SELECT SQL Core Scalar Function Corpus Next9

This slice adds parser-level `SELECT` SQL coverage for core scalar functions
inside copied Application `wp_options` queries. It is intentionally separate from
accepted direct scalar-helper corpus assertions and from later accepted scalar
operator, subquery, expression-ORDER-BY, grouped SELECT, and JSON table source
clusters.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlCoreScalarFunctionCorpusTest.php`
- `php lanes/libsqlite/examples/application-select-sql-core-scalar-functions.php --self-test`

The new tests cover scalar-function dispatch through projection, `WHERE`,
`ORDER BY`, grouped `HAVING`, and bound-parameter positions for `coalesce`,
`ifnull`, `nullif`, `typeof`, `quote`, case conversion, length/octet length,
substring, trim variants, replace, instr, concat/concat_ws, hex/unhex,
zeroblob, char/unicode, scalar min/max, and `iif`.

Dependency closure: no new support component is needed. The slice reuses the
existing bounded `SQLiteSelectSql`, `SQLiteSelectExpression`, and
`SQLiteCoreScalarFunction` components.
