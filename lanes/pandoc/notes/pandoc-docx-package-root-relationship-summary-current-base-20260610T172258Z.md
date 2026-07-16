# pandoc-docx-package-root-relationship-summary-current-base-20260610T172258Z

Slice: `plib-8p96`, DOCX OpenXML package ingestion.

This slice surfaces package-root OPC relationship role preflight summaries in
DOCX reader metadata as `docxPackageRelationships`, preserving role counts,
issue counts, and compact invalid relationship entries for reviewer handoff.

It also bounds core-properties ingestion to internal package targets. External
or invalid core-properties relationships now remain review metadata instead of
being treated as ZIP entry names.

Focused coverage adds a readable DOCX package with invalid external
core-properties and thumbnail roles plus an encrypted-package content-type
mismatch. The document body still imports, while metadata and import reports
preserve the package-root role issues.

Verification:

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 test file, 4719 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60899 assertions, 0 failures after rebase

No Pandoc, Cabal/Haskell runner, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
