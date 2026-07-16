# DOCX OpenXML Package Path Segment Position Provenance

Bead: `plib-q26em`

Slice: `pandoc-docx-openxml-package-path-segment-position-provenance`

## Scope

- Added per-segment package path position provenance to DOCX/OpenXML inventory
  entries emitted by `DocxOpenXmlReader`.
- Each package inventory entry now reports `pathSegmentPositionReviews` alongside
  existing `pathSegments`, `pathSegmentCount`, and `directoryDepth` metadata.
- Segment records include the segment index, raw segment value, normalized
  position label (`only`, `first`, `middle`, or `last`), and boolean first/last/
  only flags for bounded review handoff.

## Fixture

- Extended `summarizes docx package part path depths for review handoff` in
  `DocxOpenXmlReaderTest.php`.
- The fixture now covers both a root-only package part and a deeply nested
  package part to lock down `only`, `first`, `middle`, and `last` classifications.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 10745 assertions, 0 failures`
- Post-rebase DOCX gate:
  `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php lanes/pandoc/tests/DocxGeneratedFieldMetadataTest.php lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `4 test files, 11162 assertions, 0 failures`
- Broad lane status check:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `331 test files, 121979 assertions, 9632 failures`
  - Failures are outside this DOCX package path-position slice and appear across
    existing DocBook, Markdown, table geometry, Unicode, YAML metadata, and
    writer expectations.

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
