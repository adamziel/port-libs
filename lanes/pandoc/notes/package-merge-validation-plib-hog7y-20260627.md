# Package merge validation: plib-hog7y

Date: 2026-06-27

Parent branch: integration/pandoc-package

Folded leaf branches:

- integration/pandoc-package-docx
- integration/pandoc-package-odf
- integration/pandoc-package-zip

Scope:

- DOCX/OpenXML package provenance for relationship source directory bases, package XML root namespace declarations, XML declarations, and signature/font/thumbnail external target policy metadata.
- ODF/ODT package provenance for manifest encryption profiles, version-history sidecars, META-INF sidecars, dialog/database sidecars, embedded object media-type parameters, ZIP source records, and compact package identity role flags.
- Shared ZIP package selected-entry package-kind, selected/readable source byte-span, selected order/path-depth, and readable compression-bucket handoff summaries.

Validation:

- `php -l` passed for touched package source and test files.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/OdfDialogPackageSidecarTest.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OdfReaderDatabasePackageSidecarTest.php lanes/pandoc/tests/OdfReaderEmbeddedObjectMediaTypeParametersTest.php lanes/pandoc/tests/OdfReaderManifestEncryptionReviewTest.php lanes/pandoc/tests/OdfReaderMetaInfSidecarTest.php lanes/pandoc/tests/OdfReaderVersionPackageSidecarTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: 12 test files, 18,074 assertions, 0 failures.
