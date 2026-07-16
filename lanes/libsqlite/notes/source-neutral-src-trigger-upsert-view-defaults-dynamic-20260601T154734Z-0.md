# Source-Neutral Trigger/Upsert/View Defaults Dynamic

Slice: `source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T154734Z-0`
Base accepted HEAD: `3a5b6993a235a391c0843d9846854d33a932523d`

## Change

- Neutralized the remaining option-shaped production result keys in
  `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`.
- Renamed recursive trigger effect metadata to `target_setting`.
- Renamed recursive child row ancestry metadata to `parent_setting`.
- Extended `SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest` so the
  trigger/upsert/view/defaults source guard rejects both legacy keys.
- Updated directly coupled recursive view RETURNING tests to assert the same
  behavior through neutral setting metadata.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSourceAdmissionTest.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningRecursiveChildYieldStreamTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `4 test files, 192 assertions, 0 failures`.
- `php -l` for changed PHP files.
  - Result: all changed PHP source/test files reported no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`
  - Result: `lane-status.json valid`.
- `git diff --check -- lanes/libsqlite`
  - Result: passed with no output.

## Dependency Closure

No new support component is needed. This cleanup reuses the existing recursive
view trigger, UPSERT, savepoint, and RETURNING row-array implementation.

## Non-Overlap

This is source-neutral production cleanup only. It does not add upstream PASS
rows, runner metadata, dashboard/root edits, compatibility aliases, or new
domain-shaped wrappers.

Root harness: not run - isolated micro-slice.
