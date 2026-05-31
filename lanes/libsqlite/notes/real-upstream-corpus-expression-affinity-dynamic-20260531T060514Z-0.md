# real-upstream-corpus-expression-affinity-dynamic-20260531T060514Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicWhereB20260531T060514ZTest.php`
as an oracle-backed upstream `whereB.test` expression-affinity shard.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereB.test`
- Ported green sections: `whereB-3.*` through `whereB-8.*`.
- Focus: column comparison affinity for NONE, NUMERIC, INTEGER, and REAL
  cases, including the upstream unary-plus rule where `+column` removes column
  affinity before comparison.

Focused growth:

- 1,080 dynamic oracle cases plus ownership/dependency assertions.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicWhereB20260531T060514ZTest.php`
- Result: `1 test files, 1087 assertions, 0 failures`.

Non-overlap:

- Does not repeat accepted expression operator, cast, affinity2/types2,
  likely(), real arithmetic, real-text, LIKE/GLOB, or scalar-subquery shards.
- This shard uses `whereB.test` comparison-affinity row-admission semantics and
  specifically keeps the indexed/table-scan parity dimension from upstream.

Exclusions:

- `whereB-1.*`, `whereB-2.*`, and `whereB-9.*` exposed existing disagreement in
  the current comparison/executor path and were left out of this ready batch.
  A follow-up can either fix those comparison-affinity edges or wire this shape
  through `SQLiteSelectSql` predicate evaluation directly.

Dependency closure:

- No new support component is needed. The test reuses
  `SQLiteRealExpressionAffinityCorpusPlan`, column affinity metadata, and the
  local `sqlite3` oracle.
