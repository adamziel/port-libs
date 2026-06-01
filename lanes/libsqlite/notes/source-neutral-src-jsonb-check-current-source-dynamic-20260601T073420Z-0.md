# Source-Neutral JSONB CHECK Current-Source Dynamic

Slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T073420Z-0`
Base accepted HEAD: `0e6b89c861545d2e8159ac2fd07a33034e44e234`

## Change

- Replaced the generated JSON path index rowid fallback with schema-derived `INTEGER PRIMARY KEY` rowid alias resolution in `SQLiteGeneratedJsonPathIndexPlan`.
- Neutralized the direct JSONB generated CHECK current/next test and example from legacy table-shaped names to `app_settings`, `setting_id`, `key_name`, `key_value`, and `load_policy`.
- Added source-neutral guard coverage for the JSONB generated CHECK/generated path source pair.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteGeneratedJsonPathIndexPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteJsonbGeneratedCheckIndexCurrentNext54Test.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - no syntax errors.
- `php -l lanes/libsqlite/examples/application-jsonb-generated-check-index-current-next54.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php lanes/libsqlite/tests/SQLiteJsonbGeneratedCheckIndexCurrentNext54Test.php lanes/libsqlite/tests/SQLiteGeneratedJsonPathIndexCurrentNext31Test.php lanes/libsqlite/tests/SQLiteJsonBTreeGeneratedIndexCurrentNext38Test.php lanes/libsqlite/tests/SQLiteJsonbCoveringIndexDeleteCurrentNext50Test.php lanes/libsqlite/tests/SQLiteJsonbIndexConstraintDeleteCurrentNext51Test.php lanes/libsqlite/tests/SQLiteJsonIndexExpressionGeneratedColumnCurrentSourceNext108Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - 8 files / 505 assertions / 0 failures.
- `php lanes/libsqlite/examples/application-jsonb-generated-check-index-current-next54.php` - changes 2, rejected rowids 1, index actions 5.
- `git diff --check -- lanes/libsqlite` - passed.

## Dependency Closure

No new support component is needed. This reuses JSONB mutation, generated-column evaluation, CHECK evaluation, B-tree/index materialization, and schema-derived rowid handling.

## Non-Overlap

This is source-neutral cleanup only. It does not add runner metadata, dashboard/root edits, generated pass inflation, or duplicate accepted pager/WAL/B-tree/JSON table behavior.
