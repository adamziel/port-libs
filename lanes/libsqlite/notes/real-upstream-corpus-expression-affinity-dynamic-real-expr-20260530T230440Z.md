## real-upstream-corpus-expression-affinity-dynamic-real-expr-20260530T230440Z

- Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T230440Z-0`.
- Added focused PHP corpus test:
  `lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealConversionTest.php`.
- Upstream source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  sections `expr-13.2` through `expr-13.7` for string numeric conversion, and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
  sections `affinity3-110` through `affinity3-142` for REAL affinity division
  preservation.
- Focused behavior: oracle-backed `quote()` and `typeof()` checks over
  string-to-integer/REAL conversion expressions and REAL arithmetic using
  `+`, `-`, `*`, and `/`.
- Focused result:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealConversionTest.php`
  passed with `1 test files, 2310 assertions, 0 failures`, adding 1152
  distinct TestRunner PASS cases.
- Non-overlap: this avoids prior cast-target/clamp matrices, bitwise/null
  propagation batches, generic arithmetic integer-pair corpus, expression
  ORDER BY, types2/blob matrix, and accepted date-affinity cast batches.
- Dependency closure: no new support component is needed; the test reuses the
  existing lane-local SELECT SQL executor and the local `sqlite3` oracle.
- Follow-up: `%` over overflow-sized REAL conversion operands still emits PHP
  float-to-int warnings and diverges from SQLite remainder behavior. This
  slice excludes `%` to keep the accepted corpus green; a later behavior fix
  should target `SQLiteSelectExpression::numericValue()` remainder coercion.
