# real-upstream-corpus-upsert-returning-dynamic-20260530T211006Z-0

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T211006Z-0`

Base accepted HEAD: `bbccc1d8f736962c4f86ebb79411aec5c77c5f5a`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - Ported trigger-order behavior from `upsert2-300`, `upsert2-310`, and `upsert2-320` for rowid tables.
  - Ported the corresponding WITHOUT ROWID trigger-order behavior from `upsert2-400`, `upsert2-410`, and `upsert2-420`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - Ported temp-trigger RETURNING stream and side-effect log ordering from `returning1-11.1` through `returning1-11.7`.

## Coverage Added

- New focused file: `SQLiteRealUpstreamUpsertReturningTriggerDynamicTest.php`.
- Adds `1881` distinct focused TestRunner PASS cases and `4161` focused behavior assertions.
- Exercises native PHP UPSERT conflict-arm execution and RETURNING stream/log ordering through dynamic row sets, schema modes, update predicates, DO NOTHING conflicts, temp-trigger insert/update/delete streams, and survivor row images.

## Non-Overlap

- This does not repeat the accepted UPSERT conflict-target, catch-all priority, redundant-conflict, select-input, alias-matrix, target/tail, or broad RETURNING projection batches.
- This slice owns trigger firing/order behavior around UPSERT outcomes and RETURNING-trigger side-effect ordering from the cited upstream files.
- It uses generic row names only and adds no domain-specific libsqlite API.

## Dependency Closure

- No new support component is needed. The patch reuses the existing native PHP `SQLiteUpsertDoUpdateWherePlan` conflict-arm executor and local row-array trigger/RETURNING stream modeling.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerDynamicTest.php`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerDynamicTest.php`: `1 test files, 4161 assertions, 0 failures`.
- Family and guard verification recorded in the final handoff response.
