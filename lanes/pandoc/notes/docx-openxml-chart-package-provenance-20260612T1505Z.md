# DOCX OpenXML chart package provenance

Slice: `plib-2kf75`, DOCX OpenXML package ingestion.

This slice adds metadata-only chart package provenance to `DocxOpenXmlReader`.
Drawing `c:chart` references and unreferenced document chart relationships now
surface as `docx.chartParts` and `docx.packageProvenance.chartParts`, including
relationship ids, target query/fragment suffixes, existence state, content-type
parameter metadata, XML root validation, byte length, CRC32, SHA-256, issue-code
rollups, and `chart-part` package inventory roles.

The handoff remains inert: chart XML is not parsed into document content and
chart part bytes are not exposed as document media. Missing, external, wrong
relationship type, bad content type, unexpected root, and missing relationship id
cases stay visible for review queues through metadata-only diagnostics.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 1980 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 71921 assertions, 0 failures

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, `zip`,
`unzip`, `ZipArchive`, browser renderer, external validator, online service,
live provider test, or live-service provider test was executed.
