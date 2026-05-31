# Real upstream UPSERT RETURNING dynamic hex-yield slice

- Base accepted HEAD: `d3f35d53d135e23f73a270582d60d9916715bb54`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`, scenario `returning1-17`.
- Behavior: `INSERT INTO foo VALUES(...) ON CONFLICT(fooval) DO UPDATE SET refcnt=refcnt+1 RETURNING fooid` yields one row per VALUES term; duplicate source terms return the original rowid image and update refcounts in source order.
- Non-overlap: accepted dynamic yield batches already cover 4-position and 5-position source permutations. This slice owns 6-position deterministic source permutations with 4096 distinct 12-row VALUES streams.
- Focused PASS growth: `16386` TestRunner PASS lines from `SQLiteRealUpstreamUpsertReturningDynamicHexYieldTest.php`.
- Dependency closure: no new support component needed; this reuses `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicHexYieldTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicHexYieldTest.php` -> `1 test files, 118787 assertions, 0 failures`, `16386` PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` -> not present in this accepted-base worktree
- `git diff --check -- lanes/libsqlite`
