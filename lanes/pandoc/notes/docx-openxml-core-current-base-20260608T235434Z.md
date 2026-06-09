# DOCX/OpenXML picture nonvisual metadata handoff

Slice: `pandoc-docx-openxml-core-current-base-20260608T235434Z`
Base: `b48611b83a6995fd80354d1b5a87a4206fee1258`
Date: 2026-06-08 UTC

## Behavior

- `DocxReader` now falls back from blank `wp:docPr` image metadata to `pic:cNvPr` `descr`, `name`, and `title` fields.
- `pic:cNvPr` id/name/description/title/hidden metadata is preserved on image AST nodes as review data attributes.
- `pic:cNvPicPr` `preferRelativeResize` and bounded `a:picLocks` states are preserved as review data attributes, with true lock states also reflected as stable review classes.
- Markdown and WordPress image handoffs carry the same nonvisual metadata without shelling out to Word, LibreOffice, Pandoc, zip/unzip, or external office tooling.

## Evidence

- Baseline focused suite before the patch:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3344 assertions, 0 failures`.
- Final focused suite:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 3380 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-docx-picture-nonvisual-handoff.php --self-test`
  passed.
- PHP lint passed for:
  `lanes/pandoc/src/DocxReader.php`,
  `lanes/pandoc/tests/DocxReaderTest.php`,
  `lanes/pandoc/examples/wordpress-docx-picture-nonvisual-handoff.php`.

## Mapping Delta

- Added one mapped DOCX/OpenXML native support case.
- `mappedDocxOpenXmlCoreCases`: `33 -> 34`.
- `docxOpenXmlCoreAssertions`: `385 -> 421`.
- `benchmarkDenominator.mapped`: `2406 -> 2407`.
- `lane-status.phpPass`: `1988 -> 1989`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP ZIP/OPC package reader, DOM XML traversal, existing DrawingML media relationship handoff, Markdown image attribute writer, and WordPress image block writer.

Full upstream Pandoc runner parity remains gated on a hydrated pinned checkout and reviewed non-mutating Cabal plan. This slice intentionally did not run Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, external office tools, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This does not repeat the accepted DOCX DrawingML hyperlink, crop/transform geometry, captioned figure, background image, chart/diagram placeholder, OLE/package placeholder, glossary-local relationship, structured document tag binding, or repeating-section slices. A next non-overlapping DOCX slice could cover group-shape or connector nonvisual metadata, picture effects, chart/diagram embedded-data handoff, or additional media provenance.
