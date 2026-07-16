# ODF manifest package coverage provenance

Implemented bounded native PHP ODF/ODT package ingestion provenance for manifest
package coverage.

`OpenDocumentPackage` and `OdfReader` now expose metadata-only
`manifestPackageCoverage` summaries through compact package summaries,
package inventory, rich reader package provenance, and package identity. The
summary records manifest package-reference counts, present ZIP-backed
references, missing declared references, virtual manifest directory references,
undeclared ZIP entries, stable path lists, and issue codes without exposing
package bytes or invoking external ZIP, office, Pandoc, or validator tools.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfManifestPackageCoverageProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestPackageCoverageProvenanceTest.php` -> 1 file, 79 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestPackageCoverageProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderMissingManifestDeclaredPartsTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfReaderTest.php` -> 6 files, 7760 assertions, 0 failures after rebasing onto `origin/main`
- `php tools/run-tests.php lanes/pandoc/tests` remains baseline-red outside this ODF slice: 347 files, 127336 assertions, 9270 failures, with visible failures in `MarkdownReaderTaskListProfileSurgeTest.php`, `MarkdownReaderTest.php`, and `ZipPackageTest.php`.

Direct-format parity remains active for the broader Pandoc lane; this slice only
adds native ODF/ODT package-ingestion review metadata.
