# Real Upstream Corpus: Expression Affinity Dynamic Defaults

Session: `port-dev-sqlite-yield-dyn-real-expr-20260601T000729Z`
Base accepted HEAD: `9938ea0ca5f2430c11f7b91d23d2213507185488`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/istrue.test`
- Ported scenario cluster:
  - `istrue-500`: `DEFAULT true`, `(true)`, `false`, `(false)` with `IS TRUE` / `IS FALSE` predicates.
  - `istrue-510`: `DEFAULT (not true)` and `(not false)`.
  - `istrue-520` through `istrue-524`: `CHECK` predicates using `IS TRUE`, `IS FALSE`, `IS NOT TRUE`, and `IS NOT FALSE`.

## Behavior Delta

Added focused upstream-backed PHP coverage for dynamic DEFAULT truth values and CHECK predicates after declared column affinity is applied. The implementation now evaluates `TRUE`, `FALSE`, unary `NOT`, declared-affinity coercion for inserted defaults, and table-level `CHECK(...)` expressions during `INSERT DEFAULT VALUES`.

The new focused file adds `2314` TestRunner pass cases over `5790` behavior assertions.

## Non-Overlap

This shard avoids the existing accepted istrue/expression-affinity surfaces:

- Existing `SQLiteRealUpstreamCorpusExpressionAffinityDynamicIsTrue20260531T070514ZTest.php` covers `istrue-100` through `istrue-410` and `istrue-710`.
- Existing expression-affinity shards already cover expression text/REAL comparisons, LIKE/GLOB ranges, CAST behavior, scalar subqueries, and parser-level SELECT text execution.
- This patch does not touch JSON, WAL, VFS, B-tree, PRAGMA, trigger, runner admission, or source-neutral cleanup surfaces.

## Verification Evidence

- Red-first command before the source change:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIsTrueDefaults20260601T000729ZTest.php`
  Result: `1 test files, 5258 assertions, 1414 failures`.
- Focused command after the source change:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicIsTrueDefaults20260601T000729ZTest.php`
  Result: `1 test files, 5790 assertions, 0 failures`.
- Coupled regression:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteInsertDefaultValuesGeneratedDefaultTest.php`
  Result: `1 test files, 51 assertions, 0 failures`.

Final lint, domain guard, and diff whitespace checks are recorded in the worker final response.

## Dependency Closure

No new support component is needed. The patch reuses the existing native default-value executor, `SQLiteSelectSql` expression evaluation for CHECK predicates, and the local `sqlite3` oracle in the focused test.

## Follow-Up

Possible future non-overlap work: upstream `istrue.test` identifier-resolution scenarios around `istrue-800` through `istrue-851`, if they are still absent on the accepted head.
