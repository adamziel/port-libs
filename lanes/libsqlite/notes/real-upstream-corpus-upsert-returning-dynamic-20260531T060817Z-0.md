# real-upstream-corpus-upsert-returning-dynamic-20260531T060817Z-0

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `upsert2-300`: rowid table `ON CONFLICT DO UPDATE` fires before-insert, before-update, and after-update trigger effects.
  - `upsert2-310`: rowid table `ON CONFLICT DO NOTHING` fires only before-insert and changes no rows.
  - `upsert2-320`: rowid table `DO UPDATE ... WHERE` false branch fires only before-insert and changes no rows.
  - `upsert2-400`, `upsert2-410`, `upsert2-420`: the same trigger-order cases for `WITHOUT ROWID` tables.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-4.5`: `RETURNING` reports the post-change row image for mixed inserted/updated rows.

## Implementation

- Added `SQLiteRealUpstreamUpsertReturningTriggerTraceDynamicTest.php`.
- No production source change was needed. The test exercises the existing native `SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace()` path with generic application setting rows.
- The dynamic matrix covers 200 rowid seeds and 200 `WITHOUT ROWID` seeds. Each seed checks update trigger order, post-update `RETURNING` row image, `DO NOTHING` suppression, `WHERE` false suppression, preserved row images, and skipped-row accounting.

## Focused Evidence

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerTraceDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerTraceDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerTraceDynamicTest.php`
  - `1 test files, 5602 assertions, 0 failures`
  - `1201` focused PASS lines.

## Non-Overlap

- This slice does not repeat accepted `returning1-17` duplicate row-stream permutations, `upsert4` conflict/replace precedence, `upsert5` arm-priority matrices, `upsert2` SELECT-input yield matrices, excluded-alias scope cases, trigger-old-value regression cases, row-value `UPDATE`/`DELETE RETURNING`, or recursive trigger/view `RETURNING` helpers.
- This slice owns the `upsert2.test` trigger-order branch matrix as observed through the UPSERT `RETURNING` row-image path, using generic table/column names.

## Dependency Closure

- No new support component is needed. The patch reuses the existing native UPSERT trigger-trace and RETURNING projection helpers.
