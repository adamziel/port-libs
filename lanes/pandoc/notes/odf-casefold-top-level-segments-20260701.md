# ODF case-fold top-level segment provenance

Slice: `plib-zcw3q`

This slice adds metadata-only case-folded package top-level segment summaries to ODF/ODT package ingestion.

The handoff now flows through:

- `OpenDocumentPackage` compact package inventory and identity
- `OdfReader` rich package provenance, package identity, and document manifest provenance
- `OdfPackageCaseFoldTopLevelSegmentInventoryTest.php`

The summary groups ZIP package entries by lowercase top-level segment, preserves exact-case variant counts, duplicate case-fold groups, package directory and path-depth buckets, manifest media-family/media-type buckets, role and byte-exposure-policy buckets, entry-name lists, and largest-entry metadata. It does not expose package payload bytes and does not invoke external Pandoc, office, TeX/browser, Typst, Node, zip/unzip, validators, or live services.

Validation before rebase:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageCaseFoldTopLevelSegmentInventoryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCaseFoldTopLevelSegmentInventoryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCaseFoldTopLevelSegmentInventoryTest.php lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
