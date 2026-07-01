# DOCX OpenXML Relationship Source Case-Fold Path Segment Positions

Bead: `plib-62kmx`

Slice: `pandoc-docx-openxml-relationship-source-case-fold-path-segment-positions`

## Scope

- Added case-folded relationship-source path segment position buckets to
  `DocxOpenXmlReader` package provenance.
- New summary fields expose source counts, occurrence counts, position counts,
  parameterized/missing-content-type bucket counts, duplicate case-fold segment
  positions, and detailed `relationshipSourceCaseFoldPathSegmentPositions`.
- Each position bucket keeps raw segment variants beside case-folded segment
  counts so mixed-case source paths can be audited without reparsing `.rels`
  sidecars.

## Fixture

- Added a focused DOCX OpenXML fixture with `Word/Source/Alpha.XML` and
  `word/source/alpha.xml` relationship sources.
- The fixture locks down first, middle, and last position buckets, mixed-case
  duplicate detection, source directory/base-name/extension/content-type/role
  rollups, relationship part lists, and largest existing source provenance.

## Parity

- `relationshipSourceCaseFoldPathSegmentPositionOccurrenceCount` is checked
  against `relationshipSourcePathSegmentPositionOccurrenceCount` for the same
  source set.
- The new case-fold buckets report the same source-position coverage as the raw
  buckets while adding duplicate-variant accounting.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 12412 assertions, 0 failures`

No Pandoc binary, office suite, zip/unzip command, external validator, online
service, live provider, browser renderer, or payload-expanding external tool was
invoked.
