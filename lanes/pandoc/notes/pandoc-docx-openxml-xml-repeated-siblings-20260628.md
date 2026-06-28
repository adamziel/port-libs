# DOCX/OpenXML XML Repeated Sibling Provenance

Slice: `plib-1420t`, DOCX OpenXML package ingestion.

## Behavior

- `DocxOpenXmlReader` now records metadata-only repeated sibling element groups for XML-inspectable DOCX package parts.
- The package inventory and `packageProvenance.summary` preserve repeated sibling group counts, repeated element totals, max sibling counts, parent paths/names/namespaces, child element names/namespaces/prefixes, and first/last sibling indexes.
- The metadata does not expose XML text, attribute values, package bytes, or external targets.

## Evidence

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with `1 test files, 11125 assertions, 0 failures` after rebasing onto `origin/integration/pandoc-package-docx`.
- `php tools/run-tests.php lanes/pandoc/tests`
  was attempted after the focused pass and reported `295 test files, 118098 assertions, 9781 failures`; sampled failures were outside the DOCX OpenXML reader slice, including DocBook, HTML writer, LaTeX writer, and Markdown surge expectations. The focused repeated-sibling test also appeared as a pass in that captured run.

## Direct Format Accounting

- Added one focused DOCX/OpenXML package-ingestion regression in the existing PHP test file.
- Focused DOCX/OpenXML validation on the rebased branch now covers `11125` assertions.
- No `phpPass` or mapped upstream denominator movement is claimed for this metadata-only package provenance slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM package inspection, `ZipPackage`, OPC content type and relationship provenance, `DocxOpenXmlReader`, and the focused lane TestRunner.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, `zip`/`unzip`, `ZipArchive`, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

## Non-Overlap

This does not repeat existing XML declaration, DOCTYPE, processing-instruction, comment, CDATA, text-node whitespace, namespace declaration, entity reference, element path/depth/prefix, or element-attribute provenance. It only adds direct sibling-repeat structure metadata for XML-inspectable package parts.
