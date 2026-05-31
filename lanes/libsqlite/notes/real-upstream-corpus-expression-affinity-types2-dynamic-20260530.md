## real-upstream-corpus-expression-affinity-dynamic-20260530T235703Z-0

Base accepted HEAD: `d045774aa6bf87ca954fff751277766f57e01075`.

Added a focused real-upstream expression-affinity batch from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`.
The owned upstream sections are `types2-1.*` equality affinity comparisons
without indexes and `types2-4.*` greater-than comparison affinity cases without
indexes. The PHP port coverage expands this into a dynamic matrix of literal,
TEXT, NUMERIC, and BLOB/no-affinity comparison paths across `=`, `==`, `!=`,
`<>`, `<`, `<=`, `>`, and `>=`.

Behavior change:

- `SQLiteRealExpressionAffinityCorpusPlan::compareExpression()` now applies
  TEXT column affinity without coercing BLOB storage values to text. This keeps
  expression affinity distinct from `CAST(... AS TEXT)`, matching the upstream
  `types2.test` storage-class rules.

Focused evidence:

- New focused PASS cases: `1001`.
- New focused behavior assertions: `5005`.
- Non-overlap: this batch does not repeat the existing `expr.test`,
  `affinity2.test`, or `affinity3.test` dynamic files; it covers the
  `types2.test` comparison-affinity interaction matrix.

Dependency closure: no new support component is needed. The batch reuses the
existing native PHP expression-affinity helper and `SQLiteBlobValue`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTypes2DynamicTest.php`
  passed: `1 test files, 5005 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity3DynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTypes2DynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `4 test files, 38512 assertions, 0 failures`.
