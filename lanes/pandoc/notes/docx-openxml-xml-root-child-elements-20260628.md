# DOCX/OpenXML XML Root Child Element Provenance

Slice: `plib-g70mv`, DOCX OpenXML package ingestion.

## Behavior

- `DocxOpenXmlReader` now records metadata-only root child-element provenance for XML-inspectable DOCX package parts.
- Per-part package inventory rows and `packageProvenance.summary` preserve root direct child counts, sorted child names, namespace/local-name/prefix buckets, first and last child names, and ordered child rows.
- The metadata does not expose XML text, attribute values, package bytes, or external targets.

## Evidence

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with `1 test files, 11510 assertions, 0 failures` after rebasing onto `origin/integration/pandoc-package-docx`.

## Direct Format Accounting

- Added one focused DOCX/OpenXML package-ingestion regression in the existing PHP test file.
- Focused DOCX/OpenXML validation on the rebased branch now covers `11510` assertions.
- No `phpPass` or mapped upstream denominator movement is claimed for this metadata-only package provenance slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM package inspection, `ZipPackage`, OPC content type and relationship provenance, `DocxOpenXmlReader`, and the focused lane TestRunner.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, `zip`/`unzip`, `ZipArchive`, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

## Non-Overlap

This does not repeat existing XML declaration, DOCTYPE, processing-instruction, comment, CDATA, text-node whitespace, leaf-text, namespace declaration, entity reference, element path/depth/prefix, repeated sibling, child-shape, root attribute, or element-attribute provenance. It only adds direct root child-element order and bucket metadata for XML-inspectable package parts.
