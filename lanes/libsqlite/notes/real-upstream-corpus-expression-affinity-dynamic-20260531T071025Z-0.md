# real-upstream-corpus-expression-affinity-dynamic-20260531T071025Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicExpridx1Mismatch20260531Test.php` as an additive real upstream expression-affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expridx1.test`
- Ported sections: `expridx1` `2.0` through `2.5` WITHOUT ROWID expression-index mismatch cleanup, and `3.0` through `3.5` generated-column expression-index mismatch cleanup.

Implementation delta:

- Added `SQLiteRealExpressionAffinityCorpusPlan::expressionIndexMismatchPlan()` for generic expression-index/table-row mismatch classification.
- The helper reports missing table rows, matched rows, stale index keys, and `PRAGMA integrity_check`-style diagnostics using generic SQLite row/key terminology.

Focused coverage:

- 500 dynamic WITHOUT ROWID section-2 seeds, each with before-delete mismatch and after-delete cleanup checks.
- 260 dynamic generated-column section-3 seeds, each with before-delete mismatch and after-delete cleanup checks.
- 1 source/ownership assertion.
- Focused PASS-line growth: `+1521` TestRunner cases.
- Focused assertion count: `6085`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealExpressionAffinityCorpusPlan.php` - pass
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExpridx1Mismatch20260531Test.php` - pass
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExpridx1Mismatch20260531Test.php` - `1 test files, 6085 assertions, 0 failures`

Non-overlap:

- This does not repeat accepted REAL ULP drift behavior from `expridx1.test` sections `1.*` and `4.*`, expression arithmetic/operator matrices, boolean truthiness, LIKE/GLOB, MATCH/REGEXP, CASE/iif, affinity2/types2 comparisons, affinity3 REAL joins, date affinity, JSON, WAL, VFS, B-tree, PRAGMA, trigger, UPSERT, or source-neutral cleanup batches.
- It owns the remaining `expridx1.test` expression-index mismatch cleanup branch for this session.

Dependency closure:

- No new support component is needed. The slice reuses the existing expression-affinity corpus helper and native SQLite affinity/quote/storage-class helpers.
