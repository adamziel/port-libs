# DOCX AltChunk and Embedded Object Package Provenance

Slice: `plib-w1ga5`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now carries already-detected `w:altChunk` alternative-format imports and embedded OLE/package imports into `docx.packageProvenance`. The package review payload includes summary counters, issue-code rollups, metadata-only byte exposure policies, CRC32/SHA-256 byte provenance, content-type parameter maps, and inventory roles for `alternative-format-import`, `embedded-package`, and `embedded-object`.

This keeps importer review queues aware of package sidecars without exposing altChunk or embedded object bytes as document media. The slice does not invoke Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification after rebase onto current main `d8489677cf`:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - 1 file, 2018 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 72031 assertions, 0 failures.

Lane status delta:

- `phpPass`: `3234 -> 3235`
- `phpFail`: `0`
- `mappedDocxOpenXmlAltChunkEmbeddedPackageProvenanceCases`: `1`
- `docxOpenXmlAltChunkEmbeddedPackageProvenanceAssertions`: `40`
