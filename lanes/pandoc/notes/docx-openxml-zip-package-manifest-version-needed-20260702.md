# DOCX/OpenXML ZIP package manifest version-needed handoff

Work item: `plib-jumoj`

## Summary

`DocxOpenXmlReader` now promotes shared ZIP package-manifest
`versionNeededToExtract` rollups into DOCX/OpenXML package provenance. The
handoff exposes metadata-only `zipPackageManifest*` summary fields and matching
`packageManifest*` fields under `packageProvenance.zipPackage`, including
version-needed summary counts, distinct version lists, feature-minimum version
lists, max values, the multiple-version flag, and grouped entry summaries.

This closes a DOCX package-ingestion gap where downstream review gates could
see creator-host comparisons but still had to inspect the full shared ZIP
manifest to distinguish entries that only need ZIP 1.0 extraction from entries
that require ZIP 2.0 features such as deflate.

## Non-overlap

This slice does not change ZIP parsing, ZIP writing, OPC graph construction,
relationship policy, media extraction, document XML conversion, or payload byte
exposure. The new fixture builds an in-memory ZIP package directly in PHP and
does not invoke external ZIP tools.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageManifestVersionNeededTest.php`
- Red-first `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestVersionNeededTest.php` failed on the missing `zipPackageManifestVersionNeededToExtractSummaryCount` field.
- Focused `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestVersionNeededTest.php` passed with 1 file, 29 assertions, and 0 failures.
- Adjacent `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestVersionNeededTest.php lanes/pandoc/tests/DocxOpenXmlPackageManifestExpansionRatioBucketsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed with 3 files, 12,563 assertions, and 0 failures.
- Broad DOCX/OpenXML sweep `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php` passed with 78 files, 16,997 assertions, and 0 failures.

Direct-format parity remains active for the Pandoc lane. No external Pandoc,
office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators,
or live services were invoked.
