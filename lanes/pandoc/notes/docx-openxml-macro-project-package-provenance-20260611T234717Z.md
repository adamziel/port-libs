# DOCX OpenXML macro project package provenance

Slice: `plib-10auw`, DOCX OpenXML package ingestion core blocker.

This slice adds inert VBA macro package provenance to `DocxOpenXmlReader`.
Document-level `vbaProject` and `wordVbaData` relationships now surface under
`docx.macroProjects` and `docx.packageProvenance.macroProjects`.

The handoff records relationship id/type, target query and fragment suffixes,
resolved target parts, content-type base/parameter metadata, expected
content-type matches, byte length, CRC32/SHA1, target relationship sidecar
counts, XML root preflight for `wordVbaData`, and missing/external diagnostics.
Macro payloads remain metadata-only with `canExposeBytes=false` and
`macro-project-metadata-only`; no macro code is executed or exposed.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 1231 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc/src/DocxOpenXmlReader.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67399 assertions, 0 failures

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, zip/unzip,
browser renderer, external validator, online service, live provider test, or
live-service provider test was executed.
