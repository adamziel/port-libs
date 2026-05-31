# real-upstream-corpus-upsert-returning-dynamic-20260531T093416Z-0

Base accepted HEAD: `505e973c7fba58525b7fffcb767bf99390508892`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- `upsert1-900`: creates a view with an `INSTEAD OF INSERT` trigger.
- `upsert1-910`: rejects `INSERT INTO view ... ON CONFLICT` with `cannot UPSERT a view`.

## Behavior

This slice adds a bounded view-target preflight to `SQLiteUpsertReturningSql::execute()`. When callers provide generic view metadata and an INSERT target resolves to that view, the helper rejects top-level `ON CONFLICT` before conflict-target analysis or `RETURNING` row production, matching upstream SQLite.

The optional view metadata is intentionally generic and does not change existing table-target callers. Table UPSERT RETURNING still executes when unrelated view metadata is present.

## Coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningViewTargetDynamicTest.php`.
- Focused PASS growth: `+2004` distinct TestRunner PASS cases.
- Focused assertion growth: `6006` assertions in the new direct test file.
- PDO SQLite oracle confirms the same `cannot UPSERT a view` rejection for the equivalent RETURNING variant.

Non-overlap: existing accepted UPSERT RETURNING dynamic batches cover conflict arms, excluded aliases, trigger streams, fault paths, schema/virtual RETURNING, redundant conflict targets, multi-arm SQL text, repeated fooval, alias/default handling, and secondary constraints. This batch owns view-target UPSERT rejection from `upsert1.test` and the same rejection before RETURNING row production.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningViewTargetDynamicTest.php` passed.
- `php -r '$json = file_get_contents("lanes/libsqlite/lane-status.json"); json_decode($json, true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningViewTargetDynamicTest.php` passed: `1 test files, 6006 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningViewTargetDynamicTest.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `3 test files, 6069 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteUpsertReturningSql` identifier scanning and adds only bounded generic view-target metadata for the upstream rejection edge.
