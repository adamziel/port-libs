# OPC Central Directory Variable Field Provenance

Slice: `plib-g0ym5`

## Behavior

- Constructed `OpcRelationshipGraph::preflightZipEntryManifest()` rows now carry central-directory variable-field offsets, byte counts, and SHA-256 hashes for raw entry names, central extra fields, entry comments, and combined review fields.
- Raw `OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` rows carry the same metadata before `ZipPackage` construction succeeds.
- Both constructed and raw OPC ZIP manifest summaries now expose aggregate central-directory name, extra-field, comment, and review-field byte buckets plus entry counts.
- The new data is metadata-only package provenance. It does not expose payload bytes, extract entries, or change OPC relationship/content-type parsing.

## Evidence

- PHP lint passed for `OpcRelationshipGraph.php` and `OpenPackagingConventionsTest.php`.
- Focused OPC validation: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test file, 4722 assertions, 0 failures`.
- Shared ZIP/OPC gate: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `2 test files, 9606 assertions, 0 failures`.
- No Pandoc, office suite, TeX engine, browser, unzip/zip command, Node, external validator, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice is limited to shared ZIP/OPC central-directory variable-field provenance in OPC package manifest preflights. It does not change ZIP parsing acceptance rules, ZIP payload reads, DOCX/EPUB/ODT reader behavior, XML relationship transforms, content-type resolution, or writer output.
