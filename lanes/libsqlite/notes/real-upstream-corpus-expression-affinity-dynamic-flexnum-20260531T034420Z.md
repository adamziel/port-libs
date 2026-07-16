# real-upstream-corpus-expression-affinity-dynamic-flexnum-20260531T034420Z

- Base accepted HEAD: `ca2d3c3a4732734353ce27d70067c3ae40d81496`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`, `cast-10.1` through `cast-10.10`.
- Behavior cluster: SQLITE_AFF_FLEXNUM compound numeric behavior for `VALUES`, `UNION ALL`, derived sources, `CROSS JOIN`, and view-shaped sources. The native helper preserves each arm's integer, real, text, blob, or null storage class instead of coercing all compound arms to one numeric representation.
- Focused coverage: `SQLiteRealUpstreamCorpusExpressionAffinityDynamicFlexnum20260531T034420ZTest.php` adds 81 focused TestRunner cases covering 10 real upstream-shaped arm pairs across 8 compound source shapes.
- Non-overlap: avoids the accepted expression-affinity dynamic real modulo/operator cluster, existing `cast-9.*` derived NUMERIC storage preservation, CASE affinity, IN-list/types2 affinity matrices, expression ORDER BY, and SELECT compound ORDER/LIMIT helper families.
- Dependency closure: no new support component needed; this reuses lane-local affinity casting, SQLite value quoting, and storage-class helpers.
