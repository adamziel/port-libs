# Package merge validation: plib-dya74

Date: 2026-06-27

Parent branch: integration/pandoc-package

Folded leaf branches:

- integration/pandoc-package-docx
- integration/pandoc-package-odf
- integration/pandoc-package-zip

Scope:

- DOCX/OpenXML package provenance for signature targets, external signature policy, embedded font policy, package thumbnails, and package XML root metadata.
- ODF/ODT package provenance for ZIP source records, embedded object media-type parameters, database sidecars, and dialog sidecars.
- Shared ZIP package selected-entry handoff order and path-depth summaries.

Validation:

- `php -l` passed for touched package source and test files.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/OdfDialogPackageSidecarTest.php lanes/pandoc/tests/OdfReaderDatabasePackageSidecarTest.php lanes/pandoc/tests/OdfReaderEmbeddedObjectMediaTypeParametersTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: 8 test files, 17,615 assertions, 0 failures.
