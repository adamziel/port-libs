# real-upstream-corpus-expression-affinity-dynamic-20260530T204838Z-0

Added `SQLiteRealUpstreamExpressionAffinityRealDynamicMatrixTest.php` as a real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  - `affinity2-120`: REAL column insert affinity stores integer/text numeric inputs as real values.
  - `affinity2-210`: REAL column comparisons apply numeric affinity to TEXT/BLOB/no-affinity operands.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
  - `types2-1.*` through `types2-4.*`: literal/column equality and range comparison affinity rules.

Focused coverage:

- 6,800 literal-vs-REAL and REAL-vs-literal rowid result-set checks across `=`, `==`, `<`, `<=`, `>`, `>=`, `!=`, `<>`, `IS`, and `IS NOT`.
- 60 REAL column-pair checks against REAL, TEXT, BLOB, and unary-plus variants.
- 5 source/count assertions.
- Total focused result: `1 test files, 6865 assertions, 0 failures`.

Non-overlap:

- This batch targets REAL-affinity comparison rowid sets specifically.
- It does not repeat the accepted expression operator matrix, cast matrix, `types2` INTEGER/NUMERIC/TEXT matrix, `affinity2` fixed-column comparison batch, Unicode GLOB/LIKE/collation slices, planner expression-index range-cost work, JSON, WAL, VFS, or B-tree surfaces.
- Countable movement is focused PHP PASS-line/assertion growth only; no mapped denominator row is claimed.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealDynamicMatrixTest.php`
  - `1 test files, 6865 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The test reuses the existing `SQLiteRealExpressionAffinityCorpusPlan` insert-affinity helper and `SQLiteSelectSql` execution path, with local `sqlite3` used only as an oracle for expected rowid sets.
