# Real Upstream Corpus: Expression Affinity REAL IN

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T042100Z-0`

Accepted base: `5823f556f77d50bd49ce909acb22097fc44da229`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/in.test`
- `in-19.10` through `in-19.40`

Coverage added:

- New focused PHP test file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealInTest.php`
- Dynamic matrix over 72 REAL values, 5 SQL text spellings per value, and 5
  predicate/query forms.
- Adds 1,801 focused TestRunner PASS cases and 7,207 focused assertions.
- Exercises REAL-affinity comparison for `IN`, `NOT IN`, `=`, and filtered
  `WHERE ... IN` paths through `SQLiteSelectSql`, with `sqlite3` oracle output.
- Includes upstream expression-index admission parity by requiring sqlite3
  `CREATE INDEX i0 ON t0(c0 IN (CAST(c0 AS TEXT)))` to pass
  `pragma_integrity_check`.

Non-overlap:

- Does not repeat accepted broad `expr.test` REAL arithmetic, `e_expr-12.3`
  expression syntax, CAST prefix, type affinity matrix, expression ORDER BY,
  range-cost, JSON, WAL, VFS, B-tree, SELECT subquery, or grouped SELECT
  clusters.
- This shard owns the upstream `in.test` REAL-affinity `IN_INDEX_NOOP`
  comparison behavior only.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealInTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealInTest.php`
  - `1 test files, 7207 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The test reuses the existing
  `SQLiteSelectSql` executor, row affinity metadata, `SQLiteAffinityComparison`,
  and the local `sqlite3` oracle used by adjacent real-upstream corpus shards.
