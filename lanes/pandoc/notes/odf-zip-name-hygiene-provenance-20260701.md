# ODF ZIP Name Hygiene Provenance

ODF and ODT package review now projects `ZipPackage::nameHygienePreflight()`
through both the compact `OpenDocumentPackage` summary and the rich
`OdfReader` package provenance surface.

The projection carries metadata-only package-level counters for entries that
need name-hygiene review:

- leading or trailing whitespace segments;
- trailing-dot segments;
- Windows reserved device names;
- Windows alternate data stream syntax;
- Unicode format controls;
- Unicode bidirectional controls.

Each package part and package-identity entry also carries the segment list,
flagged segments, issue codes, and a boolean issue flag so reviewers can
identify the exact ZIP entry without exposing package bytes.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderZipNameHygieneProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderZipNameHygieneProvenanceTest.php`
  (2,547 assertions, 0 failures after rebasing onto `origin/main`)
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderZipNameHygieneProvenanceTest.php`
  (7,430 assertions, 0 failures after final rebase)
- `php tools/run-tests.php lanes/pandoc/tests`
  (126,622 assertions, 9,267 failures in unrelated DocBook, HTML writer,
  LaTeX writer, and Markdown surge tests; no ODF or ODT failures in the
  captured log)

No external Pandoc, office, or ZIP tools were used.
