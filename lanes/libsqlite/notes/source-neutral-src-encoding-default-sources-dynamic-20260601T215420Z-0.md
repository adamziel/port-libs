# source-neutral-src-encoding-default-sources-dynamic-20260601T215420Z-0

## Scope

Owned the remaining encoding default-source and directly coupled dynamic fixture
surfaces that still used domain-shaped application names in the current
source-neutral guard set.

## Source-neutral cleanup

- Extended `SQLiteEncodingSourceNeutralDefaultsTest.php` over the BLOB
  LIKE/GLOB affinity, malformed-byte GLOB, UTF-16 LIKE/GLOB affinity range,
  and UTF-16 NOCASE/RTRIM pattern helpers.
- Added direct assertions that those helpers report generic
  `main.app_settings` source names for their default current/next plans.
- Replaced old fixture vocabulary in the directly coupled encoding cursor,
  BLOB affinity, malformed GLOB, UTF-16 LIKE/GLOB range, and UTF-16
  NOCASE/RTRIM pattern tests with generic `module`, `profile`, `setting_id`,
  `key_name_bytes`, `key_value`, and `load-policy` terms.
- Preserved the byte-level assertions by updating the expected UTF-16 and
  malformed-byte hex/token values for the neutral fixture strings.
- Production source scan for the owned legacy terms was already clean and
  remained clean after this patch.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php && php -l lanes/libsqlite/tests/SQLiteEncodingCollationSourceCursorNext82Test.php && php -l lanes/libsqlite/tests/SQLiteBlobLikeGlobAffinityCurrentSourceNext234Test.php && php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityGlobCurrentSourceNext239Test.php && php -l lanes/libsqlite/tests/SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNext124Test.php && php -l lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextTest.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteEncodingCollationSourceCursorNext82Test.php lanes/libsqlite/tests/SQLiteBlobLikeGlobAffinityCurrentSourceNext234Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityGlobCurrentSourceNext239Test.php lanes/libsqlite/tests/SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNext124Test.php lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextTest.php`
  passed: `7 test files, 425 assertions, 0 failures`.
- `rg -n "wp_options|wp_sitemeta|\bwp_|WordPress|wordpress|option_id|option_name|option_value|autoload|blog_id|siteurl|blog_public|active_plugins|plugin|Plugin|PLUGIN|theme|Theme|THEME" lanes/libsqlite/src`
  returned no matches.
- `git diff --check -- lanes/libsqlite` passed.

## Status Delta

Source-neutral cleanup only. No `phpPass`, mapped-coverage, or lane-status
counter movement is claimed.

## Dependency Closure

No new support component is needed. The cleanup reuses existing encoding cursor,
BLOB affinity, malformed-byte GLOB, UTF-16 pattern decode, LIKE/GLOB, NOCASE,
RTRIM, text-affinity, and current-source invalidation helpers.
