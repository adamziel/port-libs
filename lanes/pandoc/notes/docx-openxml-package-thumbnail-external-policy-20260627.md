# DOCX OpenXML Package Thumbnail External Policy

Slice: `plib-z9zts`, DOCX/OpenXML package ingestion, 2026-06-27.

`DocxOpenXmlReader` now carries external-target policy metadata for package
thumbnail relationships. Package provenance distinguishes allowed HTTPS
thumbnail review links from unsafe local-file targets with item-level
`externalTargetKind`, `externalTargetScheme`, `externalTargetAllowed`, and
`externalTargetIssues` fields plus summary-level allowed/unsafe counters,
unsafe target lists, scheme buckets, and issue-code rollups.

External thumbnail targets remain metadata-only: no external thumbnail is
fetched, no thumbnail bytes are exposed as document media, and no image
decoding/rendering is claimed.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 10,332 assertions, and 0 failures.

Metric movement:

- `lanes/pandoc/lane-status.json` `phpPass`: 460 -> 461
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: 2305 -> 2306
- Added `mappedDocxPackageThumbnailExternalTargetPolicyCases = 1`
