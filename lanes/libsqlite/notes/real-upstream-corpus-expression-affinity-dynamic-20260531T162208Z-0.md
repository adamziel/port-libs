# real-upstream-corpus-expression-affinity-dynamic-20260531T162208Z-0

## Source truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/hexlit.test`
- Covered upstream scenarios: `hexlit-100..164`, generated `hexlit-200.$n.1..4`, and oversized-literal rejects `hexlist-400`, `hexlist-401`, `hexlist-402`, and `hexlist-410`.
- Behavior cluster: numeric hexadecimal integer SQL literals in SELECT expressions, including `0x`/`0X`, upper/lower hex digits, leading zeroes, signed 64-bit two-complement interpretation, explicit unary signs, bitwise/comparison expression use, and oversized literal rejection.

## Patch

- `SQLiteSelectSql` now parses `^[+-]?0[xX][0-9A-Fa-f]+$` integer literals before decimal/real fallback.
- Hex parsing matches SQLite's 64-bit signed token behavior:
  - `0x8000000000000000` yields `-9223372036854775808`.
  - `0xFFFFFFFFFFFFFFFF` yields `-1`.
  - `-0xFFFFFFFFFFFFFFFF` yields `1`.
  - `-0x8000000000000000` and `0x10000000000000000` reject as `hex literal too big`.
- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicHexLiteral20260531T162208ZTest.php`, which generates 260 literal variants and checks 1,040 SELECT expression cases against local `sqlite3` oracle output, plus 4 oversized-literal reject cases and a source-truth rollup.

## Evidence

- Red-first local check before the source change:
  - `php -r "require 'tools/bootstrap.php'; var_export(PortLibs\\LibSqlite\\SQLiteSelectSql::execute('SELECT quote(0x123) AS q, typeof(0x123) AS t', []));"`
  - Result: fatal `InvalidArgumentException: SQLite SELECT SQL expression 0x123 is not supported`.
- Focused verification after the source change:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicHexLiteral20260531T162208ZTest.php`
  - Result: `1 test files, 3136 assertions, 0 failures`.
  - Distinct PASS cases added: `1045`.
- Adjacent parser/evaluator regression checks:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinitySignedLiteralDynamicTest.php`
  - Result: `1 test files, 3728 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicBitwiseTest.php`
  - Result: `1 test files, 8754 assertions, 0 failures`.
- Guard/checks:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`.
  - `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - Result: no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicHexLiteral20260531T162208ZTest.php`
  - Result: no syntax errors.
  - `git diff --check -- lanes/libsqlite`
  - Result: clean.
- `lane-status.json` selected evidence moved from `3350074` to `3351119` pass / `0` fail. Mapped coverage remains `1589 / 1589`.

## Non-overlap

This slice covers actual numeric hex integer literal parsing in SELECT SQL. It does not repeat the earlier `hexlit-300`/`hexlit-301` string-literal affinity and `CAST('0x1234' AS INTEGER)` behavior, and it avoids CASE, JSON, VFS, WAL, B-tree, PRAGMA, trigger, date/time, and named-parameter expression-affinity clusters.

## Dependency closure

No new support component is needed. The implementation reuses existing SELECT expression parsing/evaluation and the focused test reuses the local `sqlite3` oracle already used by adjacent real-upstream corpus tests.
