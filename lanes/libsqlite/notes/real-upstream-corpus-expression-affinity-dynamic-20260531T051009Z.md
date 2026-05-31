# real-upstream-corpus-expression-affinity-dynamic-20260531T051009Z

Base accepted HEAD: `7174979f2808c9ccf08c3331545660695c77e192`.

Implemented focused PHP coverage for real upstream SQLite
`test/types3.test` section `types3-3.1` through `types3-3.5`.

Coverage added:

- `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypes3DualRep20260531T051009ZTest.php`
- 1,440 dynamic upstream-derived behavior cases plus 2 provenance/dependency
  cases.
- 5,767 focused assertions.
- 1,442 focused TestRunner PASS lines.

Behavior covered:

- TEXT-affinity comparison of scalar-expression values that have numeric-looking
  source text.
- Equality, range, `BETWEEN`, singleton `IN`, `IS NOT DISTINCT FROM`, and
  predicate-wrapped truth checks.
- Scalar RHS expression forms using literal text, `upper`, `lower`, `concat`,
  `printf`, `substr`, `replace`, `trim`, and explicit `CAST(... AS TEXT)`.

Non-overlap:

- Avoids accepted `affinity2.test` and `types2.test` matrix coverage.
- Avoids accepted `e_expr.test` cast-prefix, lossy-cast, scalar-subquery,
  EXISTS, real-truth, min/max, integer-boundary, LIKE/GLOB, and cast-derived
  shards.
- Does not add source APIs, generated fake upstream script names, or metadata
  admission rows.

Dependency closure:

- No new support component needed.
- Reuses native `SQLiteSelectPredicate` affinity comparison and
  `SQLiteSelectExpression` scalar dispatch already present in the libsqlite
  port.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypes3DualRep20260531T051009ZTest.php`
  passed with `1 test files, 5767 assertions, 0 failures`.
