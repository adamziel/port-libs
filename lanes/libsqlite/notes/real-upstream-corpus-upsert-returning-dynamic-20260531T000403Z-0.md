# Real Upstream UPSERT RETURNING Omitted Target Dynamic

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T000403Z-0`

Base accepted HEAD: `dd1b1090c602dc6e35c0593d57edce4faedf25d2`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test` sections `1.1` and `1.2`: omitted `ON CONFLICT DO NOTHING` suppresses both primary-key and secondary UNIQUE conflicts.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test` sections `4.1` through `4.5`: `RETURNING` emits rows only for changed INSERT/UPSERT rows.

Implemented lane movement:

- Added `SQLiteRealUpstreamUpsertReturningDoNothingOmittedTargetDynamicTest.php`.
- The test runs 1000 deterministic generic `app_settings` VALUES statements through `SQLiteUpsertReturningSql`.
- Each statement includes primary-key, unique key, tenant, and slot conflicts plus one non-conflicting insert.
- Assertions verify skipped rows, inserted rows, projected `RETURNING` rows, change counts, and omitted-target conflict target behavior.

Non-overlap:

- Existing accepted UPSERT/RETURNING batches cover conflict-arm priority, target-analysis guards, SELECT input, and long-yield DO UPDATE streams.
- This slice covers the SQL parser/executor path for omitted-target `ON CONFLICT DO NOTHING RETURNING` across all unique constraints, with no WordPress-specific names.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDoNothingOmittedTargetDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDoNothingOmittedTargetDynamicTest.php`
- Result: `1 test files, 5003 assertions, 0 failures`, 1002 PASS lines.

Dependency closure:

- No new support component needed; this reuses `SQLiteUpsertReturningSql` omitted-target DO NOTHING execution and native `RETURNING` projection.

Next suggested upstream section:

- If continuing this domain, use `returning1.test` subquery/FK ordering or `upsert4.test` expression/partial-index target-analysis sections only when they can be ported without repeating existing conflict-arm priority and long-yield batches.
