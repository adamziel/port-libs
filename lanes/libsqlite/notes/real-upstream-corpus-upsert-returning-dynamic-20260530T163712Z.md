# Real upstream corpus: UPSERT RETURNING dynamic continuation

- Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T163712Z-0`
- Accepted base: `92b65fe2933444167e639234f5a0c525e1097aec`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- Ported scenarios:
  - `upsert2.test` cases `upsert2-100`, `upsert2-110`, `upsert2-200`, `upsert2-201`, and `upsert2-210`: multi-row VALUES/SELECT-source UPSERT with `DO UPDATE ... WHERE`, repeated conflicts, target alias behavior, rowid and WITHOUT ROWID table layouts, ordered final row images, skipped rows, changes, and RETURNING step order.
  - `returning1.test` case family `17.$tn.1`: duplicate input row UPSERT increments `refcnt` and returns `fooid` in insert/update step order for main and TEMP table variants.
  - `upsert1.test` case `upsert1-1100`: explicit `ON CONFLICT(b) DO NOTHING` suppresses the rowid `ON CONFLICT REPLACE` path and emits no RETURNING row.
- Focused assertion count: `2434` for `SQLiteRealUpstreamUpsertReturningDynamicTest.php`, up from the prior real-corpus note's `1601` for this file; focused delta is `+833` assertions.
- Non-overlap: this extends the existing `upsert5.test` multi-arm coverage with `upsert2.test` SELECT-source/WHERE repeated-conflict behavior, `returning1.test` duplicate input RETURNING order, and `upsert1.test` explicit target precedence. It does not repeat trigger/savepoint RETURNING, row-value RETURNING windows, recursive trigger/view UPSERT, or the prior `upsert5.test` arm-order matrix.
- Dependency closure: no new support component is needed; the batch reuses the existing generic `SQLiteUpsertDoUpdateWherePlan` model and TestRunner.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTest.php`
  - Result: `1 test files, 2434 assertions, 0 failures`
