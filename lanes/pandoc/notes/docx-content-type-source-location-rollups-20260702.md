# DOCX Content-Type Source Location Rollups

Bead: `plib-cnuxr`

This slice extends DOCX package provenance for content-type source buckets.

- `summary.partContentTypeSources[*]` now carries `directoryCounts`, `topLevelSegmentCounts`, and `packageAreaCounts`.
- `summary` also exposes nested lookup maps:
  - `partContentTypeSourceDirectoryCounts`
  - `partContentTypeSourceTopLevelSegmentCounts`
  - `partContentTypeSourcePackageAreaCounts`
- Largest-part snapshots inside each source bucket now include `topLevelSegment` and `packageArea`.

The intent is to make default, override, and missing content-type coverage reviewable by package location without reopening package bytes.

Focused verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
