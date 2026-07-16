# Real upstream corpus date affinity dynamic 20260531T100244Z

## Upstream source

- Hydrated SQLite upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test`
- Ported section: `timediff-6-$x1$x2` and reciprocal `timediff-6-$x2$x1`
- Behavior: `timediff(A,B)` must be accepted as a `datetime()` modifier that reconstructs `A` from `B` over the upstream 2000/2001 month-boundary cross-product.

## Scope

- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff6Roundtrip20260531T100244ZTest.php`.
- The file generates the upstream 24 x 24 x 2 directional matrix, plus source-truth, application rollup, non-overlap, and dependency-closure checks.
- Focused PASS-case delta expected: `+1155` new TestRunner cases.

## Non-overlap

- Does not repeat accepted timediff-3 exact string assertions, timediff-5 generated partial-modifier grammar, date4 strftime row ranges, date19 floor/ceiling normalization, date20 truncation, or expression-affinity storage shards.
- Uses generic application labels only; no new WordPress-specific libsqlite API or scenario names.

## Dependency closure

- No new support component needed.
- Reuses existing `SQLiteCoreScalarFunction` `timediff()` / `datetime()` modifier behavior and `SQLiteRealExpressionAffinityCorpusPlan` TEXT-affinity storage checks.

## Verification

- `sqlite3 :memory: "SELECT sqlite_version(), timediff('2025-02-28','2024-02-29'), timediff('2024-02-29','2025-02-28'), datetime('2025-02-28', timediff('2024-02-29','2025-02-28'));"` -> `3.51.2|+0000-11-30 00:00:00.000|-0000-11-28 00:00:00.000|2024-02-29 00:00:00`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff6Roundtrip20260531T100244ZTest.php` -> `1 test files, 8081 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateTimediffDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteDateTimeTimediffCorpusTest.php lanes/libsqlite/tests/SQLiteSelectSqlCoreScalarFunctionCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff6Roundtrip20260531T100244ZTest.php` -> `4 test files, 9429 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/libsqlite` passed.
