## real-upstream-corpus-expression-affinity-dynamic-expr-case-20260531T091828Z

- Base accepted HEAD: `0098ded681a4eb1c42c3ee09d87f3167111f8b69`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`, `expr-case.1` through `expr-case.13`.
- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExprCase20260531T091828ZTest.php`.
- Focused PASS growth: `+1010` TestRunner cases: `1008` sqlite3-oracle dynamic cases plus source-ownership and dependency-closure checks.
- Focused assertion evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExprCase20260531T091828ZTest.php` passed with `1 test files, 2027 assertions, 0 failures`.

Behavior covered:

- Searched CASE equality from `expr-case.1..4`.
- Simple CASE integer arms from `expr-case.5` and `expr-case.9`.
- Simple CASE with `WHEN NULL` from `expr-case.6..8`.
- No-ELSE NULL fall-through from `expr-case.10`.
- Mixed text/integer ELSE storage from `expr-case.11`.
- NULL THEN result from `expr-case.12`.
- Ordered searched CASE thresholds from `expr-case.13`.
- Dynamic row contexts cover `48` `i1`/`i2` rows after INTEGER insert-affinity coercion, including upstream exact rows, NULLs, numeric strings, real-valued integers, overflow-shaped text, and nonnumeric text.

Non-overlap:

- Existing CASE coverage in this worktree owns `e_expr.test` CASE truth, base-affinity, collation, lazy evaluation, and iif-style matrices. This handoff owns the older upstream `expr.test` `expr-case.*` row-context tests, which were not directly present in lane tests or notes before this slice.
- No implementation classes, examples, WordPress-named APIs, runner metadata rows, or fabricated upstream script ids were added.

Dependency closure:

- No new support component is needed. The shard reuses existing `SQLiteSelectSql` CASE expression execution, `SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities()`, and local `sqlite3` oracle parity against the hydrated upstream test file.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExprCase20260531T091828ZTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExprCase20260531T091828ZTest.php` passed: `1 test files, 2027 assertions, 0 failures`.
- `SQLiteNoDomainSpecificApiTest.php` and `git diff --check -- lanes/libsqlite` are part of the final focused handoff verification for this worktree.
