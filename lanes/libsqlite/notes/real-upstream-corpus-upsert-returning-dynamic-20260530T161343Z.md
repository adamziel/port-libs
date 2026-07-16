# Real upstream corpus: dynamic UPSERT RETURNING

- Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T161343Z-0`
- Accepted base: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- Ported scenarios:
  - `upsert5.test` generalized UPSERT section `1.$tn.100` through `1.$tn.505`, across all six upstream table layouts.
  - `returning1.test` RETURNING projection behavior: post-DML row image, wildcard projection order, missing-column failure, and malformed alias failure.
- Focused assertion count: `1601`.
- Non-overlap: this covers generalized multi-arm UPSERT conflict routing, duplicate conflict arms, catch-all conflict arms, `DO NOTHING` suppression, and RETURNING projection over the changed row image. It does not repeat prior single `DO NOTHING RETURNING`, trigger/savepoint RETURNING, row-value RETURNING, recursive trigger/view UPSERT, or numbered current-next helper coverage.
- Dependency closure: no new support component is needed; the batch reuses the existing generic `SQLiteUpsertDoUpdateWherePlan` model and TestRunner.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTest.php`
  - Result: `1 test files, 1601 assertions, 0 failures`
