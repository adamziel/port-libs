# ODF ZIP General-Purpose Flag Provenance

ODF and ODT package review now projects `ZipPackage::generalPurposeFlagPreflight()`
through both the rich `OdfReader` package provenance surface and the compact
`OpenDocumentPackage` summary.

The metadata-only projection carries package-level counts for supported,
unsupported, UTF-8-name, data-descriptor, deflate-option, and strict-review
entries. Each package part and package identity entry also exposes normalized
general-purpose flag fields, including flag names, unsupported bits, UTF-8-name
usage, data-descriptor usage, deflate option names, strict-review status, and
issue codes.

The regression fixture builds an in-memory ODT ZIP whose `content.xml` uses
UTF-8 names, a ZIP data descriptor, and deflate option bits. No external
Pandoc, office suite, ZIP CLI, browser, TeX, Node, or external validator is
required.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderZipGeneralPurposeFlagProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderZipGeneralPurposeFlagProvenanceTest.php`
  (57 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderZipGeneralPurposeFlagProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderZipNameHygieneProvenanceTest.php`
  (2,776 assertions, 0 failures after final rebase)
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdfReaderZipGeneralPurposeFlagProvenanceTest.php`
  (5,350 assertions, 0 failures)
