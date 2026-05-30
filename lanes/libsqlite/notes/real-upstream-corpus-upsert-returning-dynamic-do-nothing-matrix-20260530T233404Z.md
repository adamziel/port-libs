# real-upstream-corpus-upsert-returning-dynamic-do-nothing-matrix-20260530T233404Z

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T233404Z-0`

Base accepted HEAD: `d7c5d7f50d0d0c3f24c91125036d23912559b628`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- `upsert1-100`: catch-all `ON CONFLICT DO NOTHING` skips primary-key and secondary-unique duplicates.
- `upsert1-101`: targeted primary-key `ON CONFLICT(a) DO NOTHING`.
- `upsert1-102`: targeted secondary unique `ON CONFLICT(b) DO NOTHING`.
- `upsert1-201`: a targeted `DO NOTHING` clause does not mask a different UNIQUE constraint failure.

PHP coverage added:

- `SQLiteRealUpstreamUpsertReturningDoNothingDynamicMatrixTest.php`
- 250 dynamic row-stream variants.
- 1,001 focused TestRunner PASS cases.
- 3,251 behavior assertions.

Non-overlap:

- This does not repeat the existing upsert5 catch-all arm-priority matrix, no-target `ON CONFLICT DO UPDATE` stream, redundant-conflict integrity cases, correlated-delete RETURNING section 20 coverage, or existing upsert2 trigger lifecycle tests.
- The slice is scoped to upsert1 target-matching `DO NOTHING` behavior as observed through the native UPSERT/RETURNING SQL executor.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDoNothingDynamicMatrixTest.php`
- Result: `1 test files, 3251 assertions, 0 failures`

Dependency closure:

- No new support component needed. This reuses the existing bounded native `SQLiteUpsertReturningSql` executor and `SQLiteUpsertDoUpdateWherePlan` row-array behavior.
