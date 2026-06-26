# DOCX Glossary Relationship Target Inventory

Slice: `plib-mxddh`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now promotes glossary document relationship target-state
counters into `docx.packageProvenance.summary`, including internal/external
relationship counts, existing/missing target counts, missing content-type
targets, and relationship target suffix counts.

The package inventory also marks local targets from glossary document
relationship sidecars with dedicated roles:

- `glossary-document-media` for local image targets.
- `glossary-document-hyperlink-target` for local hyperlink targets.

This keeps glossary building-block sidecar targets visible to importer review
without exposing hyperlink target bytes as media and without fetching external
targets.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - 1 file, 9592 assertions, 0 failures.

Metric movement:

- `phpPass`: 440 -> 441.
- `phpFail`: 0.
- Focused DOCX/OpenXML assertions: 9565 -> 9592.
- Added `summarizes docx glossary relationship target inventory roles for package review`.

Non-overlap:

- Does not change document body parsing, note/comment parsing, header/footer
  relationship parsing, ActiveX/VBA policy checks, ZIP decoding, or external
  target fetching.
- Reuses the existing native PHP OPC relationship diagnostics and package
  inventory role plumbing.
