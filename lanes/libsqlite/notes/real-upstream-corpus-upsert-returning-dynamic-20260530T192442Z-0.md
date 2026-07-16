# real-upstream-corpus-upsert-returning-dynamic-20260530T192442Z-0

Status: blocked under the hard throughput handoff floor; no ready behavior patch emitted.

Accepted base inspected: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`.

Assigned upstream domain inspected from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `upsert1.test`, `upsert2.test`, `upsert3.test`, `upsert4.test`, and `upsert5.test`
- `returning1.test`
- `upsertfault.test`
- `returningfault.test`

Current accepted overlap:

- `SQLiteRealUpstreamUpsertReturningDynamicYieldTest.php` already ports `upsert1-101`, `upsert1-120`, `upsert1-201`, `upsert1-700`, `upsert2-100`, `upsert3-130`, `upsert3-200`, `upsert3-210`, `returning1-4.2`, `returning1-4.5`, `returning1-7.7`, and `returning1-7.8`.
- `SQLiteRealUpstreamUpsertReturningDynamicStatementTest.php` already ports `returning1-17.1`, `returning1-17.2`, and `returning1-20.1` through `returning1-20.3`.
- `SQLiteRealUpstreamUpsertReturningDynamicTailTest.php`, `SQLiteRealUpstreamUpsert5FullMatrixTest.php`, `SQLiteRealUpstreamUpsert5GeneralizedMatrixTest.php`, `SQLiteRealUpstreamUpsert5ConflictMatrixCorpusTest.php`, `SQLiteRealUpstreamUpsertReturningDynamicBroadTest.php`, `SQLiteRealUpstreamUpsertReturningDynamicPriorityTest.php`, `SQLiteRealUpstreamUpsertReturningDynamicPriorityMatrixTest.php`, `SQLiteRealUpstreamUpsertReturningDynamicRedundantConflictTest.php`, `SQLiteRealUpstreamUpsertReturningDynamicTargetTest.php`, `SQLiteRealUpstreamUpsertReturningDynamicSchemaVariantsTest.php`, and related focused files already cover the high-volume `upsert4.test` / `upsert5.test` arm-order, target-priority, generated histogram, recursive RETURNING, redundant-conflict, and schema-variant surfaces.

Blocker:

The remaining non-overlapping upstream sections in this assigned family are not a clean throughput batch:

- `upsertfault.test` is fault-injection/OOM simulation over `ON CONFLICT(b,c) DO UPDATE`, not ordinary UPSERT semantics.
- `returningfault.test` is fault-injection plus eponymous virtual-table constructor/update behavior.
- `returning1.test` sections not already accepted mostly require unrelated feature domains for this slice, including virtual tables/FTS5, writable `sqlite_schema` / `sqlite_temp_schema`, trigger DDL edge handling, collation error propagation, subquery name-resolution errors, and table-valued pragma behavior.

Adding another small PHP file from these leftovers would either duplicate accepted UPSERT/RETURNING behavior or fall far below the valid `real-upstream-corpus-*` handoff gates:

- 0 new distinct TestRunner PASS cases added.
- 0 new behavior assertions added.
- 0 mapped denominator rows claimed.
- No implementation blocker was fixed that unlocks 2,000 PASS cases or 10,000 assertions.

Next larger batch to try:

Move this family into a broader `real-upstream-corpus-returning-vtab-schema-trigger` or source-neutral runner slice that explicitly owns the needed support domains:

1. virtual-table/FTS5 RETURNING result propagation from `returningfault.test` and `returning1-24.*`;
2. writable schema and temp-schema RETURNING name-resolution from `returning1-21.*` and `returning1-22.*`;
3. trigger DDL and recursive trigger RETURNING error paths from `returning1-18.*`, `returning1-19.*`, and `returning1-23.*`.

Dependency closure:

No new support component was added in this lane. A countable follow-up needs bounded native PHP support for at least one of virtual-table xUpdate/xFilter, writable schema mutation, or trigger DDL name-resolution before these upstream leftovers can be admitted honestly.

Verification:

- Root harness: not run - isolated micro-slice.
