# real-upstream-corpus-expression-affinity-dynamic-20260531T094330Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicEscapeError20260531T094330ZTest.php` as an additive real upstream expression-affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `expr-10.1` and `expr-10.2`: `LIKE ... ESCAPE` rejects empty and multi-character ESCAPE expressions with the upstream single-character error.
- `expr-5.58a` through `expr-5.68b`: valid single-character ESCAPE controls used to prove the same dynamic SQL path still evaluates accepted LIKE predicates.

Focused behavior:

- Builds a local `sqlite3` oracle for each valid single-character ESCAPE control expression.
- Verifies the native PHP `SQLiteSelectSql` executor over 1,200 distinct dynamic SQL cases.
- Each case checks the valid control result against `sqlite3`, then checks that the paired invalid ESCAPE expression throws `InvalidArgumentException` with `ESCAPE expression must be a single character`.

Expected focused movement:

- Focused TestRunner PASS-line growth: `+1202` from this file when run alone.
- Behavior assertions: `6012` from this file when run alone.
- Mapped coverage remains `1589 / 1589`; this is PASS-line growth over already mapped upstream corpus files.

Non-overlap:

- This slice owns the `expr.test` `expr-10` invalid ESCAPE arity branch.
- It avoids accepted `expr-3` text comparison, `expr-4` REAL/text affinity, `expr-5` valid LIKE/ESCAPE pattern corpus, `expr-6` GLOB, `expr-case`, `expr-14`/`expr-15` truth, `whereG` planner-hint affinity, `affinity2`, `types2`, `types3`, date, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches.

Dependency closure:

- No new support component is needed. The test reuses existing native `SQLiteSelectSql` LIKE/ESCAPE parsing, `SQLiteDatabase` text-length validation, and hydrated upstream `sqlite3` oracle controls.
