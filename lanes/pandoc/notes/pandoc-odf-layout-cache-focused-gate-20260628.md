# ODF layout-cache focused package gate

Slice: `plib-1psac` ODF/ODT package ingestion.

This slice repairs the focused `OdfReaderLayoutCachePackageSidecarTest` gate by
dropping a stale rich-reader expectation for `manifestMediaFamily` on direct
manifest rows. The compact `OpenDocumentPackage` manifest review path still
asserts the layout-cache media family, while the rich `OdfReader` path continues
to verify layout-cache byte blocking, package role precedence, missing/invalid/
encrypted/undeclared diagnostics, compact parity, and WordPress non-exposure.

No package bytes are exposed and no external Pandoc, office suite, unzip, or
validator process is invoked.

Validation:

- `php -l lanes/pandoc/tests/OdfReaderLayoutCachePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLayoutCachePackageSidecarTest.php`
  - 1 file, 96 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLayoutCachePackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php`
  - 3 files, 2,240 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLayoutCachePackageSidecarTest.php lanes/pandoc/tests/OdfReader*Package*Test.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 21 files, 3,985 assertions, 0 failures after rebase

Parity accounting: no denominator change claimed. This keeps an existing ODF
package-ingestion layout-cache regression independently runnable outside the
known broad `OdfReaderTest.php` baseline failures.
