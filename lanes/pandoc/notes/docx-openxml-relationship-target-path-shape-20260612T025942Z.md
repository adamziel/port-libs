# DOCX OpenXML Relationship Target Path Shape Provenance

This slice adds bounded native PHP DOCX/OpenXML package-ingestion provenance for
internal relationship target path shape.

Base verified: `origin/main` `d53b14e45e`.

`DocxOpenXmlReader` now annotates relationship summaries with:

- `targetParentTraversalCount` and `targetHasParentTraversal`.
- `targetStartsAtPackageRoot`.
- `sameSourcePart`.

Package summaries now include parent-traversal relationship counts, traversal
segment totals, same-source relationship counts, affected relationship-part
buckets, and ordered relationship snapshots. Relationship-type summaries also
carry parent-traversal and same-source counters so package review can see which
relationship families use those target shapes.

Operational relationship resolution is unchanged: the existing normalized target
part remains the value used for package ingestion.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 file, 1615 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 files, 69912 assertions, 0 failures.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests are invoked by this slice.
