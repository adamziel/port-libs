# Real Upstream Expression Affinity Dynamic Large

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T183105Z-0`

Base: `2b09fd94bbc734a3a9855d41884522c7a5a06914`

Added `SQLiteRealUpstreamExpressionAffinityDynamicLargeTest.php` with 1161
focused TestRunner PASS cases. Source truth is the hydrated SQLite upstream
checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
  - `cast-1.*`, `cast-3.*`, `cast-5.*`, `cast-7.*`, `cast-9.*`
  - Covered CAST result storage class, scalar/text/blob casts, unary numeric
    coercion, and CAST subexpression comparison behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
  - `types2-1.*` through `types2-5.*`
  - Covered literal comparison affinity and no-affinity column comparison rows
    for the bounded row-array SQL executor.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicLargeTest.php`
  - `1 test files, 1161 assertions, 0 failures`

Non-overlap:

- This does not repeat accepted `affinity2`, `affinity3`, `e_expr`, or existing
  `types2` bulk rows. It adds a separate `cast.test` dynamic expression matrix
  and keeps the row-array `types2` scope to currently green no-affinity rows.

Follow-up blockers found by red oracle run:

- Exact SQLite `quote()` formatting for REAL casts around large mantissas,
  `0.0`, and `-0.0`.
- Declared TEXT/NUMERIC column affinity metadata is not yet propagated through
  the bounded `SQLiteSelectSql` row-array `WHERE` evaluator for the remaining
  `types2` comparison matrix.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP expression, SELECT SQL, and affinity helpers plus the local
`sqlite3` binary only as an oracle for focused tests.
