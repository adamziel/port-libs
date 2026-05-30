# real-upstream-corpus-expression-affinity-dynamic-20260530T194127Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Added `SQLiteRealUpstreamExpressionAffinityTypes2OracleDynamicTest.php`, a
real upstream corpus batch sourced from hydrated SQLite
`test/types2.test`.

Coverage:

- Ports the `types2.test` manifest type and column-affinity comparison family
  over the upstream `t2(i INTEGER, n NUMERIC, t TEXT, o XBLOBY)` fixture shape.
- Uses local `sqlite3` once as the oracle for actual `typeof()` and `quote()`
  storage classes after insertion, then compares the native PHP affinity
  comparator over 12 upstream inserted values, 4 affinity columns, 6 RHS
  literal forms, and 4 operators.
- Focused growth: 1,153 distinct TestRunner PASS cases and 9,219 behavior
  assertions.

Non-overlap:

- This does not repeat the accepted `e_expr.test` operator/null matrix or the
  smaller accepted `types2-2/3/6` rowset assertions in
  `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`.
- The new owned surface is oracle-backed per-row/per-column manifest affinity
  comparison behavior from `types2.test`; it is not metadata admission, fake
  upstream ids, a domain-specific API, or a loop around one static record.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTypes2OracleDynamicTest.php`
  - `1 test files, 9219 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP `SQLiteRealExpressionAffinityCorpusPlan` comparator and the
local SQLite CLI only as a test oracle for hydrated upstream `types2.test`
semantics.
