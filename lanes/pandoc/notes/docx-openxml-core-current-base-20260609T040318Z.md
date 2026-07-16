# DOCX OpenXML Current-Base Chart Plot Metadata

Slice: `pandoc-docx-openxml-core-current-base-20260609T040318Z`

Base: `72a53fe4cb43f993ddc490102ccddab53f4ddfb1`

## Behavior

- Preserves bounded DOCX chart plot metadata on inert DrawingML chart placeholders:
  - plot count, plot type, normalized plot class suffix, series count, grouping, bar direction, and vary-colors.
  - data-label position, display flags, separator, number format, and source-linked flag.
  - legend position, overlay, and manual layout x/y/width/height values.
- Emits the metadata through the existing Pandoc-like AST attributes, Markdown writer attributes, and WordPress block writer attributes.
- Keeps chart rendering, spreadsheet/workbook execution, and external conversion out of scope.

## Evidence

- Baseline before this patch: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 3818 assertions, 0 failures`
- Red-first focused probe after adding expectations:
  - `1 test files, 3712 assertions, 2 failures`
  - Missing chart plot/data-label/legend classes and metadata on chart placeholders.
- Final focused verification:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 3873 assertions, 0 failures`
- Focused delta:
  - `phpPass`: `2267 -> 2268`
  - `benchmarkDenominator.mapped`: `2671 -> 2672`
  - `mappedDocxOpenXmlCoreCases`: `33 -> 34`
  - `docxOpenXmlCoreAssertions`: `385 -> 440`
  - New focused assertions: `+55`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP ZIP/OPC package reader, relationship loader, DOM-based DOCX chart part parsing, Pandoc-like AST, Markdown writer, WordPress block writer, focused lane test runner, and existing DOCX chart data handoff example. Full upstream Pandoc DOCX runner parity remains an upstream-runner dependency and was not attempted.

## Non-Overlap

This does not repeat accepted DOCX chart style/color, chart title/series/axis, embedded workbook provenance, content control, comments/endnotes, tracked formatting revisions, connector/group shape, DrawingML geometry, image, or ODT/EPUB/doctemplate work. The new behavior is limited to chart plot, data-label, and legend metadata.

## Exclusions

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, spreadsheet tool, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was run.

## Next

Next DOCX/OpenXML work should target a non-overlapping bounded reader gap such as chart theme/style inheritance, table/numbering style interactions, or richer DrawingML text properties.
