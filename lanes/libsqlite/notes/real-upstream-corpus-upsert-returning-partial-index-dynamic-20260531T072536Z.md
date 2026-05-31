# Real Upstream Corpus: UPSERT RETURNING Partial Index Dynamic

Session: `port-dev-sqlite-yield-dyn-real-upsert-20260531T072536Z`
Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T072536Z-0`
Base accepted HEAD: `9d0b0fe07345f3693373fb79bddfe1aa2564a7a2`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- Ported sections: `upsert4-4.1.2`, `upsert4-4.1.3`, `upsert4-4.1.5`, and `upsert4-4.2.3`.
- Behavior cluster: partial unique-index conflict target execution combined with UPSERT RETURNING row streams.

## Non-Overlap

Existing accepted UPSERT/RETURNING dynamic batches cover aliases, target-first priority, catch-all arms, repeated conflicts, redundant conflicts, omitted targets, trigger ordering, and SELECT-source streams. This slice owns the partial unique-index predicate decision path: matching partial predicates skip or update through the conflict arm, while non-matching predicates insert distinct rows and RETURNING emits only changed rows.

## Focused Evidence

- Extended `lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningPartialIndexDynamicTest.php` with one new per-case RETURNING statement-metadata assertion family.
- Focused run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningPartialIndexDynamicTest.php`
- Result: `1 test files, 13503 assertions, 0 failures`.
- PASS-line growth over the accepted file: +1,000 distinct focused TestRunner PASS cases.

## Dependency Closure

No new support component is needed. The test reuses `SQLiteUpsertReturningDynamicCorpusPlan::upsert4PartialIndexReturningDynamicCases()`, `SQLiteUpsertReturningDynamicPlan` partial-index predicate execution, generic row-array uniqueness, and RETURNING changed-row projection.
