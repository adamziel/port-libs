# real-upstream-corpus-upsert-returning-dynamic-20260531T073907Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test` section `17.1` / `17.2` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test` section `1300`.
- Ported behavior: repeated `INSERT ... VALUES ... ON CONFLICT DO UPDATE ... RETURNING fooid` emits one RETURNING row per changed input row in statement order for both main and temp table shapes, and repeated large duplicate UPSERT updates preserve trigger-visible old/new payload equality.
- Focused PHP growth: `SQLiteRealUpstreamCorpusUpsertReturningRepeatedFoovalDynamicTest.php` adds 5,003 TestRunner cases over 1,000 dynamic real-upstream-inspired variants plus source/dependency guards.
- Non-overlap: existing accepted UPSERT RETURNING batches cover target priority, partial predicates, trigger histograms, repeated conflict WHERE streams, excluded aliasing, and expression assignments. This slice isolates returning1 section 17 repeated `fooval` row streams and the upsert1 section 1300 large-payload trigger row-image regression.
- Dependency closure: no new support component needed; the slice reuses generic native conflict-arm yield trace and RETURNING projection helpers.
