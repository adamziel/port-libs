# DOCX OpenXML Relationship Source Directory Base Names

Slice: `plib-2zgxa`

## Summary

`DocxOpenXmlReader::packageProvenanceSummary()` now exposes relationship
source directory basename buckets for DOCX/OpenXML package review:

- `relationshipSourceDirectoryBaseNameCounts` groups relationship sidecar
  sources by the final segment of their source directory;
- existing and non-existing source counts are split into
  `relationshipSourceExistingDirectoryBaseNameCounts` and
  `relationshipSourceNonExistingDirectoryBaseNameCounts`;
- duplicate basename groups are listed in
  `duplicateRelationshipSourceDirectoryBaseNames`;
- detailed `relationshipSourceDirectoryBaseNames` rows retain source
  directories, source parts, relationship parts, depth/content-type/role
  counts, byte totals, and the largest existing source part.

This is metadata-only provenance for bounded native DOCX package ingestion. It
does not read relationship target payloads beyond the existing source-part
inventory already used by the DOCX package summary.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 12457 assertions, 0 failures`

No Pandoc binary, office suite, TeX/browser engine, Node tooling, external
validator, online service, live provider test, `zip`/`unzip` command, or
payload-expanding external tool was invoked.
