# source-neutral-src-encoding-default-sources-dynamic-20260601T202956Z-1

## Scope

Owned the remaining stale domain-shaped default pattern sources in
`SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan`.

## Source-neutral cleanup

- Replaced hardcoded default LIKE patterns based on `plugin`/`Plugin`/`PLUGIN`
  with generic `module`/`Module`/`MODULE` patterns across the encoding
  collation affinity LIKE helpers.
- Replaced the fixed `PLUGIN_*` secondary GLOB probe with a probe derived from
  the parsed LIKE prefix, preserving explicit caller behavior without keeping
  a domain-shaped production literal.
- Updated the directly coupled default-caller examples to use neutral module
  setting fixtures.
- Extended `SQLiteEncodingSourceNeutralDefaultsTest.php` so encoding source
  defaults reject stale plugin-shaped terms and verify all default patterns
  through generic application setting rows.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
  passed: `1 test files, 56 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext237Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext242Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext248Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext254Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext258Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext260Test.php`
  passed: `9 test files, 761 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `1 test files, 8 assertions, 0 failures`.
- Updated examples passed:
  `application-option-value-like-escape-affinity-current-source-next237.php --self-test`,
  `application-embedded-nul-like-current-source-next242.php`,
  `application-dangling-escape-like-current-source-next245.php`,
  `application-nonascii-escape-like-current-source-next248.php`,
  `application-option-name-rtrim-like-current-source-next249.php`,
  `application-encoding-collation-affinity-like-current-source-next254.php --self-test`,
  `application-rtrim-like-residual-next260.php`,
  `application-case-sensitive-like-current-source-next258.php`, and
  `application-encoding-binary-like-current-source-next259.php --self-test`.
- `php -l` passed for all changed PHP files.
- `git diff --check -- lanes/libsqlite` passed.

## Status delta

Source-neutral cleanup only. No `phpPass`, mapped-coverage, or lane-status
counter movement is claimed.

## Dependency closure

No new support component is needed. The cleanup reuses existing LIKE ESCAPE,
case-sensitive LIKE, GLOB, text-affinity, UTF-16 decoding, RTRIM collation, and
current-source invalidation helpers.
