# real-upstream-corpus-upsert-returning-dynamic long yield

- Session: `port-dev-sqlite-yield-dyn-real-upsert-20260530T235452Z`
- Base accepted HEAD: `c18695783d58d6f8245967de682828c93b145ece`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`, scenario `returning1-17`
- Ported behavior: multi-row `INSERT ... ON CONFLICT DO UPDATE RETURNING` yields one row per input term, duplicate values return the original rowid image, and the final duplicate refcounts match the source stream.
- Non-overlap: existing accepted `SQLiteRealUpstreamUpsertReturningDynamicLargeYieldTest.php` covers 4-position source permutations. This slice covers 5-position source permutations expanded into 1024 deterministic 10-row streams.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicLongYieldTest.php` passed with `1 test files, 25603 assertions, 0 failures` and `4098` PASS lines.
- Expected dashboard movement: PASS-line growth only, `+4098`; mapped denominator coverage remains `1589 / 1589`.
- Dependency closure: no new support component needed; this reuses `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()`.
- Root harness: not run; isolated micro-slice.
