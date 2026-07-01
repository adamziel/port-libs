# ZIP Package Manifest Path Segment Position Summaries

Slice: `plib-ww7ky`

## Summary

`OpcRelationshipGraph::preflightZipEntryManifest()` now carries OPC-level
aggregates for the shared ZIP package path segment position provenance:

- `pathSegmentPositionRoleEntryCounts` groups ZIP path position coverage by OPC
  role after content-type and relationship classification;
- `pathSegmentPositionHandoffKindEntryCounts` groups the same position coverage
  by importer handoff kind;
- `entryNamesByPathSegmentPositionRole` and
  `entryNamesByPathSegmentPositionHandoffKind` preserve sorted entry-name
  provenance for reviewer handoff;
- the existing per-entry `pathSegmentPositionReviews` and raw ZIP package
  segment-position summaries remain the source data.

This keeps DOCX/OpenXML, EPUB3, ODF/ODT, and raw OPC callers on the same native
PHP ZIP package topology provenance while also showing how path depth maps to
OPC roles and byte handoff categories before format-specific XML parsing.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `2 test files, 10532 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, `zip`/`unzip` command, `ZipArchive`, online service, live
provider test, or payload-expanding external tool was invoked.
