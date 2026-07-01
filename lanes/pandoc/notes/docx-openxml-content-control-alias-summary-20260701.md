# DOCX/OpenXML content control alias summary

## Scope

- Added package-level content control alias rollups for DOCX/OpenXML ingestion.
- `docx.contentControls` now reports `aliasCount` and `aliases` beside the existing tag, binding, source-type, and scope summaries.
- `docx.packageProvenance.summary` mirrors those values as `contentControlAliasCount` and `contentControlAliases` for reviewer handoff.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

No external Pandoc, office-suite, TeX/browser-engine, unzip/zip, Jupyter, Node, or external-validator tooling was used.
