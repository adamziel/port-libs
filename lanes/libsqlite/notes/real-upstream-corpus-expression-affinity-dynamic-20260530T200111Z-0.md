# Real upstream corpus expression affinity dynamic

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T200111Z-0`

Accepted base: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
  - `affinity3-100..142`: automatic-index and nested LEFT/RIGHT view queries
    preserve REAL affinity for `apr` before `/ 100` and `typeof(apr)`.
  - `affinity3-200..260`: `USING(id)` joins through a UNION view and a
    materialized copy preserve mixed-source comparison affinity, so integer
    `1` does not match text data id `'1'` while text ids continue to match.

## Behavior Added

- Extended `SQLiteRealExpressionAffinityCorpusPlan` with bounded affinity3
  helpers for REAL view rows and mixed-source `USING(id)` join rows.
- Fixed the join comparison path to keep no-affinity compound/materialized
  source behavior instead of forcing TEXT affinity onto both sides.
- Added `SQLiteRealUpstreamExpressionAffinity3DynamicTest.php` with 1,017
  distinct focused TestRunner cases and 8,104 behavior assertions.

## Non-Overlap

This batch does not repeat the accepted `expr.test`, `e_expr.test`,
`cast.test`, `types2.test`, or `affinity2.test` expression-affinity batches.
It owns only the upstream `affinity3.test` automatic-index/view and mixed
source join affinity cases.

## Verification

- Red-first focused command before the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity3DynamicTest.php`
  - Result: `1 test files, 8092 assertions, 4 failures`
  - Failing behavior: integer `1` incorrectly matched text data id `'1'` for
    `idmap` and `mzed` under both automatic-index modes.
- Focused command after the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity3DynamicTest.php`
  - Result: `1 test files, 8104 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/libsqlite/src/SQLiteRealExpressionAffinityCorpusPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity3DynamicTest.php`

## Dependency Closure

No new support component is needed. The slice reuses existing bounded
expression-affinity comparison and insert-affinity helpers.
