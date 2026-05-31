# real-upstream-corpus-upsert-returning-dynamic-20260531T053650Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-11.1`: TEMP-table `INSERT ... RETURNING` yields changed rows while AFTER INSERT trigger effects are logged separately.
  - `returning1-11.2`: TEMP-table `UPDATE ... RETURNING` yields the post-update row image before the trigger log is read.
  - `returning1-11.3` and `returning1-11.4`: TEMP-table `DELETE ... RETURNING` yields deleted row images and BEFORE DELETE trigger log entries in statement order.
  - `returning1-11.5` and `returning1-11.6`: a separate TEMP table preserves RETURNING projection order while its BEFORE INSERT trigger records side effects.
  - `returning1-11.7`: chained INSERT/UPDATE/DELETE RETURNING streams on a third TEMP table remain grouped by statement, with trigger logs visible afterward.

## Local Behavior

- Added `SQLiteReturningTempTriggerPlan`, a generic native PHP model for RETURNING streams on TEMP tables with BEFORE/AFTER trigger side effects.
- Added `SQLiteRealUpstreamReturningTempTriggerDynamicTest.php` with 1000 deterministic variants and 4002 TestRunner PASS cases.
- The dynamic cases verify changed-row RETURNING stream order, trigger log order, projection order, final table state, upstream dependency labels, and malformed-input guards.

## Verification

- `php -l lanes/libsqlite/src/SQLiteReturningTempTriggerPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningTempTriggerDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningTempTriggerDynamicTest.php`
  - Result: `1 test files, 11004 assertions, 0 failures`

## Non-Overlap

This slice avoids accepted UPSERT arm ordering, upsert4 conflict target behavior, excluded-alias SQL, correlated DELETE RETURNING, trigger DDL error ordering, writable-schema RETURNING, virtual-table RETURNING, trigger/FK RETURNING, and row-value UPDATE/DELETE RETURNING batches. It owns the TEMP-table RETURNING plus trigger-side-effect ordering behavior from `returning1.test` 11.1-11.7.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local PHP test infrastructure and adds a bounded native PHP RETURNING/trigger ordering plan under `lanes/libsqlite/src`.
