# Real Upstream Corpus Expression Affinity Dynamic

Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`.

This slice extends `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
with real SQLite upstream `test/types2.test` coverage:

- `types2-5.15` through `types2-5.43`: remaining `IN (...)` affinity rows,
  including no-affinity columns, literal-only membership, and RHS column values
  whose column affinity is ignored by SQLite IN-list comparison rules.
- `types2-7.1` through `types2-7.15`: scalar `IN (SELECT...)` affinity checks
  against `t3`, including left-side update/insert affinity before comparison.
- `types2-8.1` through `types2-8.9`: indexed rowid sets for `IN (SELECT...)`
  against `t4` over INTEGER, NUMERIC, TEXT, and BLOB/no-affinity columns.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
  passed `1 test files, 6921 assertions, 0 failures`.
- The added block contributes 57 distinct focused TestRunner PASS cases and
  more than 500 behavior assertions from real upstream `types2.test` subtests.

Non-overlap:

- This does not repeat the existing `types2-1.*`, `types2-2.*`, `types2-3.*`,
  `types2-4.*`, or earlier `types2-5.1` through `types2-5.14` / `types2-6.*`
  coverage already present in the expression-affinity dynamic test.
- No metadata-only rows, generated fake upstream script names, WordPress-shaped
  source names, or status-only counter changes were added.

Dependency closure:

- No new support component is needed. The existing
  `SQLiteRealExpressionAffinityCorpusPlan` comparison and insert-affinity
  helpers are reused for upstream-shaped scalar and rowset fixtures.
