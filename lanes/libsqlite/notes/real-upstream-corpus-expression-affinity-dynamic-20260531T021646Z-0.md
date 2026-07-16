# real-upstream-corpus-expression-affinity-dynamic-20260531T021646Z-0

- Base accepted HEAD: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`, scenarios `cast-9.4` through `cast-9.11`.
- Ported behavior: derived-table `CAST(... AS NUMERIC)` values preserve SQLite INTEGER vs REAL storage class across both join orientations, then remain stable through arithmetic, comparison, truthiness, `IS NULL`, `NOT NULL`, and recast expressions.
- PHP behavior fix: `SQLiteSelectSql` now accepts projection expressions using SQLite shorthand `expr NULL` and `expr NOT NULL`, mapping them to the existing `IS NULL` / `IS NOT NULL` predicate evaluator.
- Focused growth: `1009` focused TestRunner PASS cases, `1127` assertions.
- Non-overlap: does not repeat accepted real-prefix conversion, CASE affinity, expression precedence/operator matrices, truth aggregate, or signed-literal batches; this shard specifically owns cast-derived JOIN boundary storage-class preservation.
- Dependency closure: no new support component needed; it reuses the existing bounded `SQLiteSelectSql` executor and local `sqlite3` oracle already used by neighboring real-upstream expression-affinity tests.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCastDerived20260531T021646ZTest.php` -> `1 test files, 1127 assertions, 0 failures`.
