# Real Upstream Corpus Expression Affinity Dynamic 20260601T114050Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`.
- Upstream scenarios covered: `expr-16.100`, `expr-16.101`, and `expr-16.102`.
- Behavior: SQLite internal `implies_nonnull_row()` expression probe over a null-extended `LEFT JOIN` row. The accepted upstream cases distinguish a comparison whose constant-false sides do not imply a real row from comparisons that become `NULL` when the right or both sides depend on row columns.
- Implementation: `SQLiteSelectExpression` now recognizes the internal upstream probe and evaluates the predicate against a null-extended row while preserving ordinary core scalar-function dispatch for all other functions.
- Focused coverage: `SQLiteRealUpstreamCorpusExpressionAffinityDynamicImpliesNonnullRow20260601Test.php` adds 1,000 dynamic behavior cases plus one source-range case and one non-overlap/dependency-closure case.
- Non-overlap: this owns only `expr.test` `expr-16` internal expression-probe behavior. It avoids accepted `expr-1` arithmetic/null/overflow, `expr-2` REAL, `expr-3` text comparison, `expr-4` affinity comparison, `expr-5` LIKE, `expr-6` GLOB, `expr-case`, `expr-10` ESCAPE errors, `expr-11` integer-boundary literals, `expr-14/15` truth, `e_expr` CASE/CAST/LIKE/GLOB/EXISTS/subquery, `affinity2`, `types2`, `types3`, date, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches.
- Dependency closure: no new support component is needed. The patch reuses `SQLiteSelectSql` parsing and adds bounded internal-probe evaluation in the native PHP expression executor.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicImpliesNonnullRow20260601Test.php` - passed, `1 test files, 3010 assertions, 0 failures`.
- Root harness: not run - isolated micro-slice.
