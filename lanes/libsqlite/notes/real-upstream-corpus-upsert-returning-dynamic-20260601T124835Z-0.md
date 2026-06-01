# real-upstream-corpus-upsert-returning-dynamic-20260601T124835Z-0

Lane: libsqlite
Base accepted HEAD: 704eae59a88752ecf27635aa23232c135e0688b2

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/insert4.test`
- Upstream sections: `insert4-10.2`, `insert4-10.3`, `insert4-10.4`
- Behavior: `INSERT INTO x SELECT * FROM t8` uses the transfer optimization before and after `PRAGMA integrity_check`, but `INSERT INTO x SELECT * FROM t8 RETURNING *` leaves `sqlite3_xferopt_count` at `0`.

## Patch

- Extended `SQLiteReturningTransferPlan` with `insertSelectXferOptimizationDecision()`.
- Added `SQLiteRealUpstreamInsert4ReturningXferDynamicTest.php` with 1000 dynamic behavior cases plus source, malformed-input, and dependency-closure tests.
- Updated `lane-status.json` by +1003 focused PASS cases: `5892006 -> 5893009`.

## Non-overlap

This does not repeat the existing `returning1-16.0` transfer row-image coverage. That accepted coverage proves `INSERT INTO target SELECT * FROM source RETURNING *` emits inserted rows. This slice adds the separate `insert4.test` optimizer boundary: `PRAGMA integrity_check` must not disable the xfer optimization, while a `RETURNING` clause must.

## Verification

- Red-first check: `method_exists(SQLiteReturningTransferPlan, insertSelectXferOptimizationDecision)` returned `missing` before implementation.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamInsert4ReturningXferDynamicTest.php`
  - `1 test files, 31017 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningTransferDynamicTest.php`
  - `1 test files, 11013 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 6 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteReturningTransferPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamInsert4ReturningXferDynamicTest.php`
  - `No syntax errors detected`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Dependency closure

No new support component is needed. The slice reuses the existing generic row-array transfer plan and the hydrated upstream `insert4.test` source file.
