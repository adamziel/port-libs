# source-neutral-src-compound-window-defaults-dynamic-20260601T105150Z-0

## Scope

- Neutralized the row-id defaults for the contiguous row-value RETURNING window
  retry family in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`.
- Replaced the production default `option_id` row-id surface with `setting_id`
  for:
  - `executeRetryWindowPlan`
  - `executeReturningWindowRollbackRetry`
  - `executeReturningWindowDigests`
  - `executeCurrentRowWindowFrames`
  - `executeReplayPairWindow`
  - `executeStatementWindowMetrics`
- Made the window-row id key follow the configured row-id column so generic
  callers receive `setting_id` rows without a legacy `option_id` output key.
- Updated directly coupled legacy fixture tests/examples to pass `option_id`
  explicitly, preserving their accepted behavior while removing the production
  default.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteWindowRowValueUpsertSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext235Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext238Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 9 files / 504 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php`: 1 file / 8 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext235Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext238Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Test.php`: 6 files / 436 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 file / 5 assertions / 0 failures.
- Example smokes passed for `application-rowvalue-window-current-source-next232.php`, `application-rowvalue-returning-window-current-source-next233.php`, `application-rowvalue-returning-window-current-source-next235.php`, `application-rowvalue-returning-window-current-source-next236.php`, `application-rowvalue-returning-window-current-source-next238.php`, and `application-rowvalue-returning-window-current-source-next239.php`.
- `php -l` over changed PHP files passed.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`: `lane-status.json valid`.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. This reuses the existing row-id resolver,
UPDATE/DELETE RETURNING executor, savepoint replay, and bounded window metadata
helpers.

## Non-Overlap

This is source-neutral cleanup only. It does not add upstream PASS rows,
metadata-only runner rows, or a new behavior corpus. It removes one production
default family and preserves existing legacy fixture behavior through explicit
caller configuration.
