# real-upstream-corpus-expression-affinity-dynamic-20260530T225530Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`

Added `SQLiteRealUpstreamExpressionAffinityDynamic0Test.php` with real upstream
expression-affinity coverage for REAL arithmetic through views, storage-class
type checks, unary expression behavior, remainder integer affinity, CAST target
affinity, and 1000 dynamic expression cases that vary REAL division, NUMERIC
casts, unary plus, and arithmetic composition.

Focused evidence: `php tools/run-tests.php
lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamic0Test.php`
passes with `1 test files, 5155 assertions, 0 failures`. Expected dashboard
movement: focused TestRunner PASS-line growth only. No mapped denominator
change is claimed.

Dependency closure: no new support component needed; this uses existing
`SQLiteSelectExpression`, `SQLiteCoreScalarFunction::typeof()`, and existing
TestRunner infrastructure.
