# real-upstream-corpus-expression-null-comparison-dynamic-20260530T192832Z

- Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`.
- Upstream scenario: `e_expr-8.*` NULL-aware `IS`, `IS NOT`, `==`, and `!=` comparison behavior.
- New focused test: `lanes/libsqlite/tests/SQLiteRealUpstreamExpressionNullComparisonDynamicTest.php`.
- Coverage shape: 12 literal storage-class representatives x 12 literal representatives x 4 comparison operators x 2 projections (`quote`, `typeof`) = 1,152 oracle-backed dynamic comparison cases, plus one ownership assertion case.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionNullComparisonDynamicTest.php` passed with `1 test files, 1157 assertions, 0 failures` and 1,153 selected PASS lines.
- Non-overlap: this owns the `e_expr.test` section-8 NULL comparison matrix. It avoids the existing expression-affinity dynamic cast/preference/precedence matrix, the accepted `types2.test` affinity bulk shard, date-affinity slices, SQL expression ORDER BY, grouped SELECT text, JSON, WAL, VFS, B-tree, and source-neutral cleanup surfaces.
- Dependency closure: no new support component needed; this reuses the local sqlite3 oracle, `SQLiteSelectSql` constant SELECT dispatch, and existing scalar `quote()`/`typeof()` expression support.
