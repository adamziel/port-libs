# source-neutral-src-encoding-default-sources-dynamic-20260601T231845Z-0

## Scope

Owned source-neutral hardening for the encoding/default-source scan after
earlier cleanup had already removed the legacy production-source terms.

## Source-neutral cleanup

- Expanded `SQLiteEncodingSourceNeutralDefaultsTest.php` so the production
  source scan is derived from the current encoding/collation/LIKE/GLOB source
  inventory instead of a stale fixed list.
- Kept the explicitly owned dynamic/default helper outliers in the same guard.
- Added coverage assertions proving the scan includes representative shared
  helpers such as `SQLiteAffinityComparison`, `SQLiteGlobCursor`, and the large
  UTF-16 NOCASE/RTRIM source helper.
- The production source scan remains clean for legacy `wp_options`,
  `option_*`, `blog_id`, `autoload`, and plugin/theme/site literals.

## Status delta

Source-neutral guard hardening only. No `phpPass`, mapped-coverage, or
lane-status counter movement is claimed.

## Dependency closure

No new support component is needed. The guard reuses the existing focused PHP
test harness and the current native encoding source files.
