# source-neutral-src-option-table-defaults-dynamic-20260601T170111Z-0

## Scope

- Neutralized the next contiguous row-value RETURNING window publication
  default-signature group in
  `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`.
- Replaced public default row-id parameters from the legacy option-shaped
  default to `setting_id` for:
  - `executeTransitionChainWindow`
  - `executeYieldGateWindow`
  - `executeFilteredReleaseWindow`
  - `executeExcludeGroupWindow`
  - `executePublicationResumeBarrier`
  - `executeChunkedYieldResumeWindow`
  - `executeExcludeTiesWindowPlan`
  - `executeSourceDigestHandoff`
  - `executePublicationWindowFence`
  - `executeChunkCursorReleaseWindow`
- Replaced the next251 default source-epoch strings from `wp-*` wording to
  generic `app-*` wording.
- Threaded the existing generic `SQLiteRowIdColumn` resolver through those
  methods so the neutral `setting_id` default resolves from arbitrary row
  lists, while explicit legacy fixture calls remain explicit.
- Expanded the source-neutral compound/window guard to cover these signatures
  and added a generic `app_settings` yield-gate default behavior assertion.
- Updated the directly coupled malformed-rowid fixture for next252 to pass the
  legacy row-id column explicitly.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`:
  no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php`:
  no syntax errors.
- `git diff --name-only -- lanes/libsqlite | rg '\.php$' | xargs -r -n1 php -l`:
  no syntax errors in the changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php`:
  `1 test files, 17 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php`:
  `1 test files, 46 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext248Test.php lanes/libsqlite/tests/SQLiteRowValueChunkedYieldResumeWindowTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext250Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Test.php lanes/libsqlite/tests/SQLiteRowValueChunkCursorReleaseWindowTest.php`:
  `10 test files, 656 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php`:
  `2 test files, 24 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. This reuses the existing pure-PHP row-id
resolver, row-value UPDATE/DELETE RETURNING executor, savepoint retry images,
and row-value/window publication helpers.

## Non-Overlap

This is source-neutral cleanup only. It does not add upstream corpus rows,
dashboard counters, release-runner metadata, compatibility aliases, or a new
wrapper API. The patch is bounded to the next244-next253 row-value window
publication default-signature group; later row-value publication methods still
have separate legacy defaults for follow-up source-neutral slices.

Root harness: not run - isolated micro-slice.
