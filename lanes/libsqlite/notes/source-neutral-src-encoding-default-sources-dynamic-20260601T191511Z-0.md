# source-neutral-src-encoding-default-sources-dynamic-20260601T191511Z-0

## Scope

Owned the remaining directly coupled encoding source-cursor fixture for
`SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan()`.

## Red-first evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationSourceCursorNext82Test.php`
  failed before cleanup with `1 test files, 59 assertions, 1 failures`.
- The failing case still fed legacy copied-option fields into the neutral
  source API: `option_id`, `option_name_bytes`, and `autoload`.
- The production helper already required `setting_id`, `key_name_bytes`, and
  `text_encoding`, so no compatibility alias was added.

## Source-neutral cleanup

- Replaced the cursor payload and direct `keyValueRowKeyScan()` rows with
  `setting_id`, `key_name`, `key_name_bytes`, and `load_policy`.
- Replaced the legacy `WP_LOCALE` rejected-collation fixture with
  `APP_LOCALE`.
- Extended `SQLiteEncodingSourceNeutralDefaultsTest.php` so this direct cursor
  fixture is guarded against reintroducing the legacy option/autoload names.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationSourceCursorNext82Test.php`
  -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
  -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationSourceCursorNext82Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
  -> `2 test files, 63 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  -> `1 test files, 8 assertions, 0 failures`.
- `rg -n "wp_options|wp_sitemeta|\bwp_|WordPress|wordpress|option_id|option_name|option_value|autoload|blog_id|WP_LOCALE" lanes/libsqlite/src`
  -> no matches.
- `git diff --check -- lanes/libsqlite`
  -> clean.

## Status delta

Source-neutral cleanup only. No `phpPass`, mapped coverage, or lane-status
counter movement is claimed.

## Dependency closure

No new support component is needed. This reuses the existing native encoding
source cursor and LIKE/GLOB collation planning helpers.
