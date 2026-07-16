# real-upstream-corpus-pragma-schema-dynamic-20260530T235728Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T235728Z-0`

Base accepted HEAD: `d045774aa6bf87ca954fff751277766f57e01075`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragmafault.test`
- `pragmafault-1.0`: creates `t1(a, b, CHECK(a!=b))` and inserts two valid rows.
- `pragmafault-1`: restores the database and runs `PRAGMA integrity_check` under OOM fault injection, expecting successful recovery.

## Changes

- Added `SQLitePragmaFaultIntegrityPlan`, a bounded native model for `PRAGMA integrity_check` over a simple table CHECK constraint with recoverable fault checkpoints.
- Added `SQLiteRealUpstreamPragmaFaultIntegrityDynamicTest.php` with 1,004 distinct focused TestRunner cases and 13,013 assertions.

## Non-Overlap

This does not repeat accepted schemafault view expansion coverage, PRAGMA schema/table/index/list metadata batches, data-version/cache-spill/runtime PRAGMA batches, or schema5/schema6 CREATE TABLE layout coverage. It owns the separate upstream `pragmafault.test` integrity-check fault path.

## Dependency Closure

No new support component is required. This reuses the lane-local native PRAGMA/schema planning style and adds only bounded CHECK-constraint integrity behavior needed by the upstream fault simulation section.

## Verification

- `php -l lanes/libsqlite/src/SQLitePragmaFaultIntegrityPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaFaultIntegrityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaFaultIntegrityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaFaultIntegrityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaFaultDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
