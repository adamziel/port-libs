# Real upstream corpus expression affinity dynamic

Micro-slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T165455Z-0`

Status: behavior-backed parser/executor growth from hydrated upstream SQLite
`test/e_expr.test`.

Ported upstream scenarios:

- `e_expr-7.*`: result storage-class matrix for `||`, arithmetic, bitwise,
  comparison, equality, `IS` / `IS NOT`, `LIKE`, and `GLOB` binary operators
  over the 13 upstream literal operands. The bounded PHP port excludes only
  `AND` / `OR` short-circuit rows, application-defined `MATCH` / `REGEXP`, and
  shift tokens that the current expression scanner does not parse yet.
- `e_expr-9.10` through `e_expr-9.21`: built-in `NOCASE` `COLLATE`
  precedence for equality, inequality, `IS`, and `IS NOT`, including the
  parenthesized comparison cases where `COLLATE` applies after the comparison.

Focused assertion count: `4120` assertions in `3613` PHP TestRunner PASS
cases for `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`. This
adds `3392` non-overlapping PASS cases over the prior `221` PASS cases in the
same focused file. Expected `phpPass` movement: `198691 -> 202083`. Mapped
upstream denominator coverage is unchanged because this ports more behavior
from an already hydrated upstream expression corpus file rather than admitting
a new denominator row.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
  - `1 test files, 4120 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses the
native PHP SELECT parser, expression evaluator, predicate evaluator,
`typeof()` dispatch, blob values, numeric coercion, LIKE/GLOB matching, and
built-in collations.

Non-overlap: extends the accepted expression/affinity corpus file into
`e_expr-7.*` and `e_expr-9.10` through `e_expr-9.21`; it avoids accepted
expression ORDER BY, grouped SELECT, subquery, JSON table, WAL/VFS, B-tree,
Unicode GLOB, and source-neutral cleanup surfaces.
