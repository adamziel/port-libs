# pandoc-docx-openxml-package-thumbnails-current-base-20260611T231509Z

Slice: `plib-drie6`, DOCX OpenXML package ingestion.

This slice adds metadata-only package thumbnail provenance to
`DocxOpenXmlReader`. Package-root thumbnail relationships now surface as
`docx.packageThumbnails` and `docx.packageProvenance.packageThumbnails` with
relationship ids, target query/fragment suffixes, content-type parameter
metadata, byte length, CRC32, existence state, target relationship-sidecar
flags, and package summary counts.

The reviewer policy remains inert: thumbnail bytes are not copied into the AST
or document media handoff, and entries carry
`package-thumbnail-metadata-only` plus `canExposeAsDocumentMedia=false`.
Diagnostics align with OPC thumbnail preflight vocabulary for external,
missing, invalid content-type, multiple source relationships, and target
sidecar cases.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 1158 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67077 assertions, 0 failures

No Pandoc, Cabal/Haskell runner, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
