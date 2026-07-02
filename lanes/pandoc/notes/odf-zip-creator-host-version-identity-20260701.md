# ODF ZIP creator host version identity

OpenDocumentPackage and OdfReader now carry metadata-only ZIP creator host/version rollups from the shared ZipPackage preflights into compact and rich package provenance and identity.

The handoff preserves host-system summaries, creator-version comparison buckets, below-needed known/unknown counts, unknown-host entries, and below-needed entry records without exposing package payload bytes or invoking external ZIP tools, office suites, validators, or live services.

Validation for plib-ofp8c passed on 2026-07-01:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipCreatorHostVersionIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipCreatorHostVersionIdentityTest.php` with 1 file, 90 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipCreatorHostVersionIdentityTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php` with 3 files, 2,429 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/Odf*Test.php lanes/pandoc/tests/OpenDocument*Test.php lanes/pandoc/tests/OdtReaderTest.php` with 62 files, 12,394 assertions, 0 failures
