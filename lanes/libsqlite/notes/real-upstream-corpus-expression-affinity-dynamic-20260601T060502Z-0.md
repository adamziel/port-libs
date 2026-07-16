# real-upstream-corpus-expression-affinity-dynamic-20260601T060502Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Upstream sections: `expr-15.1.*` through `expr-15.6.*`.

Behavior ported:
- Bound double `NaN`, `-NaN`, `NaN0`, `-NaN0`, `Inf`, and `-Inf` rows are compared against a local SQLite3 `bindValue(..., SQLITE3_FLOAT)` oracle.
- `SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities()` now stores bound `NaN` as SQL `NULL`, matching SQLite insert semantics even for columns with no declared affinity.
- `SQLiteRealExpressionAffinityCorpusPlan::quote()` now formats positive and negative infinity as SQLite reports them: `9.0e+999` and `-9.0e+999`.
- The focused corpus expands the four upstream expr-14 truth-coercion invariant queries across 42 dynamic rowsets and all 6 bound-double variants: `42 * 6 * 4 = 1008` oracle-backed behavior cases, plus source/normalization/dependency guard cases.

Non-overlap:
- This slice does not repeat the existing `expr-14.1..14.4` finite truth aggregate dynamic corpus.
- It does not repeat the existing broad `expr-7.2..7.74` WHERE predicate/function-call dynamic corpus, current-time literal, DQS, hex-literal, boolean truthiness, integer-boundary, IN-list, IN-subquery, raise-function, or parameter-token expression-affinity batches.

Focused verification:
- `php -l lanes/libsqlite/src/SQLiteRealExpressionAffinityCorpusPlan.php`
  - PASS: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNanInfTruth20260601T060502ZTest.php`
  - PASS: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNanInfTruth20260601T060502ZTest.php`
  - PASS: `1 test files, 5053 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNanInfTruth20260601T060502ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTruthAggregateDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - PASS: `3 test files, 8663 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - PASS: `lane-status json ok`.
- `git diff --check -- lanes/libsqlite`
  - PASS: no whitespace errors.

Dashboard delta:
- `phpPass`: `5619423 -> 5620434` (`+1011` focused PASS cases).
- Mapped denominator unchanged: `1589 / 1589`.

Dependency closure:
- No new support component is needed. The slice reuses the existing expression-affinity corpus helper, `SQLiteSelectSql` truth evaluation, and the local SQLite3 extension only as an oracle for upstream `sqlite3_bind_double` behavior.

Root harness:
- Not run - isolated micro-slice.
