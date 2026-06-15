# Pandoc ODF Manifest Generic Media Family

- `OpenDocumentPackage` now classifies generic `application/octet-stream` manifest entries by package path when the path identifies image/audio/video media resources.
- `Pictures/generic.png` and `Media/generic.ogg` surface as `image` and `audio` in manifest review family buckets, media parts, and package inventory roles; non-media octet-stream package entries remain `binary`.
- No Pandoc, office suite, TeX/browser engine, Node, zip/unzip, external validator, online service, live provider test, or live-service provider test was invoked.

Status delta:

- `phpPass`: 3655 -> 3656, `phpFail`: 0
- mapped upstream cases: 3692 -> 3693
- `mappedOdfManifestMediaFamilyMatrixCases`: 10 -> 11
- `odfManifestMediaFamilyMatrixAssertions`: 43 -> 51

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` passed 1 file, 1517 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php` passed 5 files, 6701 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 46 files, 86201 assertions, 0 failures.
