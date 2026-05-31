Real upstream corpus expression-affinity IN-list expression slice

- Session: port-dev-sqlite-yield-dyn-real-expr-20260531T020639Z
- Base accepted HEAD: 140040354d7e1605b310a7ab46633d1e6e437f9b
- Upstream source truth: /home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test
- Upstream scenario: e_expr.test e_expr-12.3.78 through e_expr-12.3.84 scalar EXPR IN/NOT IN list syntax.
- Behavior implemented: SQLiteSelectSql now parses projected scalar IN and NOT IN expressions and reuses the existing SQLiteSelectPredicate IN evaluator, including NULL propagation, CAST results, COLLATE values, CASE expressions, and wrapper expressions such as IS TRUE, IS FALSE, coalesce(), and CASE WHEN.
- Focused PHP growth: 2561 TestRunner PASS cases in SQLiteRealUpstreamExpressionAffinityInListExpressionDynamicTest.php.
- Focused assertions: 10247 assertions.
- Verification: php -l lanes/libsqlite/src/SQLiteSelectSql.php; php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInListExpressionDynamicTest.php; php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityInListExpressionDynamicTest.php; git diff --check -- lanes/libsqlite.
- Dependency closure: no new support component needed; this reuses the hydrated upstream .test corpus, sqlite3 oracle for expected values, and existing libsqlite SELECT/predicate evaluators.
- Non-overlap: avoids accepted expression REAL/cast/affinity3 arithmetic, expression ORDER BY, subquery IN/EXISTS, LIKE/GLOB, and prior scalar WHERE predicate clusters; this slice is specifically scalar projected IN-list expression syntax.
