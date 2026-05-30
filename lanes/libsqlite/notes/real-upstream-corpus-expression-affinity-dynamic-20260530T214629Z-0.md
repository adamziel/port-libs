# real-upstream-corpus-expression-affinity-dynamic-20260530T214629Z-0

Base accepted HEAD: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Ported scenario: `expr-1.86` through `expr-1.92` BETWEEN and NOT BETWEEN expression truth table, including NULL-bound three-valued logic.

## Behavior

- Fixed `SQLiteSelectSql` BETWEEN parsing so only a top-level BETWEEN operator is treated as a BETWEEN expression or predicate.
- This fixes parser-level projection expressions such as `quote(5 BETWEEN 3 AND 8)`, which previously attempted to parse `quote(5` as the BETWEEN left operand before function-call handling.
- Added `SQLiteRealUpstreamExpressionBetweenDynamicTest.php`, an oracle-backed dynamic corpus over 12 literal storage-class variants, BETWEEN/NOT BETWEEN, and `quote()`/`typeof()` projections.

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionBetweenDynamicTest.php`
  - `1 test files, 6917 assertions, 0 failures`
  - 6913 selected PASS lines: 6912 oracle-backed dynamic cases plus 1 source/count ownership case.

## Non-overlap

This does not repeat accepted expression affinity boolean/cast-target coverage, `types2` matrices, affinity2/affinity3 storage rules, LIKE/GLOB behavior, NULL comparison equality behavior, expression ORDER BY, grouped SELECT text, or metadata-only upstream admission rows. The slice targets a real parser/executor bug for upstream `expr.test` BETWEEN expressions inside scalar projection functions.

## Dependency closure

No new support component is needed. The slice reuses the existing bounded `SQLiteSelectSql`, `SQLiteSelectPredicate`, and local `sqlite3` oracle pattern already used by adjacent real-upstream expression corpus tests.
