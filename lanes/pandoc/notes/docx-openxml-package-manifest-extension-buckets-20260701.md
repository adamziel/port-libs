# DOCX Package Manifest Extension Buckets

Slice: `plib-jvm62`

## Scope

DOCX/OpenXML package ingestion now carries ZIP package-manifest package part extension buckets through `DocxOpenXmlReader` metadata. The handoff reuses the shared `ZipPackage::packageManifestPreflight()` extension summaries and keeps package bytes metadata-only.

## Handoff

- `packageProvenance.zipPackage` mirrors `extensionlessPackagePartCount`, `hasExtensionlessPackageParts`, `packagePartExtensionSummaryCount`, `packagePartExtensions`, and `packagePartExtensionSummaries` as `packageManifest*` fields.
- `packageProvenance.summary` mirrors the same fields under `zipPackageManifest*` keys for reviewer dashboards and importer gates.
- Per-entry `zipPackage.entries`/`byPackagePath` rows now carry `packagePartExtension`, `packagePartExtensionKey`, and `extensionlessPackagePart` from package manifest entries.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- conflict-marker scan of changed files
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` with 1 file, 11917 assertions, 0 failures.
