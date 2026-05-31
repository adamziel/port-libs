## real-upstream-corpus-expression-affinity-dynamic-20260531T041656Z-0

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`, sections `affinity3-100` through `affinity3-142`.
- Behavior owned: REAL affinity preservation through view and join materialization with automatic-index-style probes. Integer-looking APR values must project as `real`, so `typeof(apr)` remains `real` and division by `100` remains real-valued.
- Non-overlap: avoids the existing `expr.test` `expr-2.1..2.28` arithmetic shard and the accepted Unicode GLOB / LIKE / generic expression real-truth batches. This slice is specifically `affinity3.test` view/join REAL affinity preservation.
- Focused assertions: `SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealAffinity3Test.php` adds 1203 focused TestRunner PASS cases over 1200 dynamic upstream-backed cases plus count, invalid-size, and dependency-closure guards.
- Dependency closure: no new support component needed; reuses lane-local REAL affinity coercion, storage-class comparison, and dynamic corpus helpers.
