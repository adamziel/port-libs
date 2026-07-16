# DOCX/OpenXML relationship target extension buckets

Slice: `pandoc-docx-openxml-relationship-target-extension-buckets`

## Scope

- Adds package-level relationship target extension buckets to `DocxOpenXmlReader`.
- Groups internal relationship targets by target part extension, including extensionless package targets.
- Preserves target directories, content-type base/source counts, relationship type counts, source parts, relationship parts, relationship ids, target parts, missing/parameterized counts, and largest existing target byte/digest metadata.
- Keeps external relationship targets out of target-extension package buckets.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result after rebase: `1 test files, 8437 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: `260 test files, 180259 assertions, 0 failures`

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, Word, LibreOffice, office suites, zip/unzip, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests were invoked.
