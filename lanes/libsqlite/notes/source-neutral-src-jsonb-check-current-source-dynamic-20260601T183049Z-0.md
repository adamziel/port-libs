# Source-Neutral JSONB CHECK Current-Source Dynamic

Slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T183049Z-0`
Base accepted HEAD: `40d6f27c381f784fcddbd3d62959e60b9072d7b4`

## Change

- Neutralized the direct JSONB CHECK and generated CHECK/index fixtures from
  plugin-shaped payloads to generic module payloads while preserving the same
  JSONB CHECK, logical CHECK, NOT IN, NOT BETWEEN, generated-column, partial
  index, and rejection/admission assertions.
- Renamed the generated CHECK/index helper variable from `decodeOption54` to
  `decodeSetting54`.
- Extended the focused JSONB CHECK source-neutral guard to cover the direct
  JSONB CHECK tests and examples so they reject `wp_`, `option_*`,
  `autoload`, `blog_id`, and plugin-shaped fixture text.

## Evidence

- `php -l` for all changed PHP files: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php lanes/libsqlite/tests/SQLiteJsonbGeneratedCheckIndexCurrentNext54Test.php lanes/libsqlite/tests/SQLiteJsonbTableGeneratedCascadeCurrentNext53Test.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - 8 files / 470 assertions / 0 failures.
- Example smoke loop over `application-jsonb-check-current-next64.php`,
  `application-jsonb-check-current-next67.php`,
  `application-jsonb-check-current-next68.php`,
  `application-jsonb-check-current-next69.php`, and
  `application-jsonb-generated-check-index-current-next54.php` passed. Outputs
  kept the same changes/rejectedChanges shape and now report module CHECK SQL.

## Dependency Closure

No new support component is needed. This reuses existing JSONB mutation,
CHECK evaluation, generated-column evaluation, partial-index maintenance, and
schema-derived rowid handling.

## Non-Overlap

This is source-neutral fixture/guard cleanup for the JSONB CHECK current-source
family. It does not add upstream runner metadata, dashboard edits, new PASS
inflation, or duplicate JSONB CHECK behavior.

Root harness: not run - isolated micro-slice.
