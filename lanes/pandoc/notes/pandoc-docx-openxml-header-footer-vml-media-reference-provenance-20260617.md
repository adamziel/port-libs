# DOCX/OpenXML Header/Footer VML Media Reference Provenance

Date: 2026-06-17
Bead: plib-g4pox
Slice: pandoc-docx-openxml-header-footer-vml-media-reference-provenance
Base: origin/main 947f1a3a5a

## Scope

This slice keeps the work inside DOCX/OpenXML package ingestion. `DocxOpenXmlReader`
now records whether header/footer image relationship declarations are actually
referenced from the source part and whether the source reference came from
DrawingML `a:blip` (`r:embed` or `r:link`) or legacy VML `v:imagedata`
relationship attributes.

The reader preserves the existing relationship-sidecar metadata while adding:

- aggregate referenced, orphaned, drawing-reference, and VML-reference counts;
- per-relationship `referenced`, `orphaned`, `referenceKind`, and
  `referenceKinds` fields;
- package summary counters for the new header/footer media reference buckets.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed 1 file, 6645 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 260 files, 178467 assertions, 0 failures.

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, office suites,
Word, LibreOffice, zip/unzip, browser renderers, Node tooling, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
