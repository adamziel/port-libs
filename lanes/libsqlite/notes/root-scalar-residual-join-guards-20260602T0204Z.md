# Root Scalar Residual Join Guards 2026-06-02T0204Z

Micro-slice: `libsqlite-current-root-failures-scalar-functions-residual-predicates-joins-20260602T0204Z`

Base accepted HEAD: `237d5f4b8e36df3db6c68956f219939b05a1e90f`

## Behavior

- Tightened direct `datetime()` malformed primary time-value handling for non-date text such as `bad-date`, while preserving accepted NULL results for invalid modifiers and malformed time-shaped strings.
- Required an application-defined callback for column-based `MATCH`/`REGEXP` residual predicate evaluation, while preserving literal/literal fallback behavior and existing callback operand ordering.
- Rejected non-empty LEFT JOIN null-extension when the caller omits right-side column names, while preserving empty-left short-circuit behavior.

## Evidence

- Before patch: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` reported `1 test files, 9419 assertions, 9 failures`.
- Focused new guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRootScalarPredicateJoinGuardTest.php` reported `1 test files, 7 assertions, 0 failures`.
- Adjacent compatibility bundle: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRootScalarPredicateJoinGuardTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityMatchRegexpDynamic20260531T042519ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityWeekdayDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimeOnlyDefault20260531T064107ZTest.php lanes/libsqlite/tests/SQLiteDateTimeTimediffCorpusTest.php` reported `5 test files, 14653 assertions, 0 failures`.
- Broad after patch: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` reported `1 test files, 9434 assertions, 6 failures`.

## Remaining Exclusions

This patch intentionally does not address the six remaining broad failures:

- builtin window ranking/value function guard
- grouped aggregate result ordering
- scalar subquery expression row counts
- compound SELECT boolean result typing
- SELECT query-plan scalar value validation
- compound SELECT SQL text ordering

## Dependency Closure

No new support component is needed. The slice reuses the existing core scalar date/time parser, residual predicate evaluator, and SELECT join helper.
