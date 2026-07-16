# real-upstream-corpus-expression-affinity-dynamic-20260530T215339Z-0

Status: focused real-upstream corpus PASS growth for SQLite expression collation binding and storage-class observation.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Covered sections:
  - `e_expr-9.*`: postfix `COLLATE` binding on comparison operands versus a parenthesized comparison result.
  - `e_expr-10.*`: `typeof()` and `quote()` observation of expression result storage classes.

Added coverage:

- `SQLiteRealUpstreamEExprCollationDynamicTest.php`
- 6000 oracle-backed dynamic comparison cases across:
  - `BINARY`, `NOCASE`, and `RTRIM` collations;
  - `<`, `<=`, `>`, `>=`, `=`, `==`, `!=`, `<>`, `IS`, and `IS NOT`;
  - text, padded text, numeric-text, integer, real, and `NULL` operands;
  - both `lhs OP rhs COLLATE name` and `(lhs OP rhs) COLLATE name`.
- 1 ownership/countability PASS case.
- Focused result: `1 test files / 18005 assertions / 0 failures / 6001 PASS lines`.

Non-overlap:

- This does not repeat the accepted expression-affinity shards for `affinity2`, `affinity3`, `types2`, broad CAST target affinity, LIKE/GLOB, BETWEEN, NULL comparison, expression precedence, SQL expression `ORDER BY`, grouped SELECT text, date affinity, or B-tree numeric affinity.
- The owned behavior is real upstream `e_expr.test` collation-postfix binding and result storage-class observation through the parser-level SELECT executor using `sqlite3` oracle output.

Dependency closure:

- No new support component is needed. This slice reuses the existing bounded `SQLiteSelectSql` parser/executor, scalar `quote()`/`typeof()` dispatch, predicate collation handling, and local `sqlite3` oracle path already used by real upstream expression-affinity tests.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamEExprCollationDynamicTest.php`
  - `1 test files, 18005 assertions, 0 failures`
