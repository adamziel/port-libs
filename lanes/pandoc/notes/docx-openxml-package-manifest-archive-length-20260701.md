# DOCX OpenXML ZIP Package Manifest Archive Length

## Slice

`plib-z9zts` carries the ZIP package manifest `archiveLength` value through
`DocxOpenXmlReader` package provenance for native DOCX/OpenXML package
ingestion.

## Behavior

- `packageProvenance.zipPackage.packageManifestArchiveLength` mirrors the
  bounded `ZipPackage::packageManifestPreflight()` archive length.
- `packageProvenance.summary.zipPackageManifestArchiveLength` exposes the same
  metadata-only value next to existing archive byte counts and SHA-256 hashes.
- Existing package byte exposure policy remains unchanged:
  `docx-zip-entry-metadata-only`, with package bytes blocked.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 11500 assertions, 0 failures.

No Pandoc, office suite, TeX/PDF engine, browser, zip/unzip, Jupyter, Node, or
external validator was invoked.
