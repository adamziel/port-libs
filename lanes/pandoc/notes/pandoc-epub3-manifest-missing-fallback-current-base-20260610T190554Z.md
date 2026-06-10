# Pandoc EPUB3 Missing Manifest Fallback Review Slice - 2026-06-10T190554Z

Bead: plib-hmdw4.

Scope:
- Adds compact EPUB3 OPF manifest preflight coverage for non-core media-type resources that declare neither `fallback` nor `fallback-style`.
- Surfaces them as review-only `missingFallbackItems` with `missing-manifest-fallback-for-non-core-media-type` diagnostics, without changing existing `fallbackCount` semantics.

Verification:
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 file / 992 assertions / 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files / 61885 assertions / 0 failures

No Pandoc, EPUBCheck, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were executed.
