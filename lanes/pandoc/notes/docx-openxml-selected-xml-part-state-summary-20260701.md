# DOCX/OpenXML selected XML part state summary

## Scope

- Mirrored existing selected OpenXML part aggregate state counts into `packageProvenance.summary`.
- Added summary fields for existing selected parts, relationship-selected parts, missing required or referenced parts, valid and invalid roots, unexpected content types, and missing content types.
- Kept the review metadata-only: selected XML part bytes remain represented through existing byte counts, CRC32, and SHA-256 provenance.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

No external Pandoc, office-suite, TeX/browser-engine, unzip/zip, Jupyter, Node, or external-validator tooling was used.
