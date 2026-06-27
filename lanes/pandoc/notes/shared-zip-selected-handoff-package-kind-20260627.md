# Shared ZIP Selected Handoff Package Kind

Slice: `plib-ww7ky`, shared ZIP/OPC package primitives.

`ZipPackage::entryHandoffPreflight()` now carries `packagePartKind` per selected entry and aggregates `selectedPackagePartKindSummaries` / `handoffPackagePartKindSummaries` before DOCX, EPUB, and ODT readers expose selected package bytes. The classifier separates content types, root and part relationship files, metadata sidecars, mimetype records, directories, media, markup parts, generic package parts, and extensionless entries.

The selected/handoff summaries include entry counts, file/directory counts, compressed and uncompressed byte totals, roles, and entry names. Blocked oversized media entries still contribute to selected package-kind accounting but are excluded from readable handoff summaries.

Mapped accounting:
- `phpPass`: `458 -> 459`
- `benchmarkDenominator.mapped`: `2304 -> 2305`
- `mappedSharedZipSelectedHandoffPackageKindCases`: `1`

Validation:
- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - 1 file, 4,979 assertions, 0 failures

No Pandoc, office suites, browser engines, `zip`/`unzip`, `ZipArchive`, external validators, online services, live provider tests, or live-service provider tests were invoked.
