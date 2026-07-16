# DOCX/OpenXML Relationship Source Digests

Hook: `plib-9st2i`, Pandoc DOCX OpenXML package ingestion core blocker slice.

This slice keeps the work bounded to native PHP DOCX/OpenXML package provenance. `DocxOpenXmlReader` now exposes digest-bearing relationship source part summaries in package provenance:

- existing source parts report source bytes, CRC32, SHA-256, content type provenance, roles, relationship counts, and largest-source rollups;
- package-root and missing relationship sources remain metadata-only with null digests;
- relationship parts used as relationship sources are included in the same source-part digest rollup.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 file, 2929 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 46 files, 87858 assertions, 0 failures.

Parity accounting:

- `phpPass`: `3708 -> 3709`
- mapped upstream manifest cases: `3732 -> 3733`
- `mappedDocxOpenXmlRelationshipSourceDigestCases`: `1`
- `docxOpenXmlRelationshipSourceDigestAssertions`: `39`
