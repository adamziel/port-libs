# DOCX OpenXML Current-Base Theme Color Scheme

Slice: `pandoc-docx-openxml-core-current-base-20260609T043140Z`

Base: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`

## Behavior

- Parses bounded DrawingML `a:clrScheme` entries from the DOCX theme part.
- Preserves scheme name, palette entries, color kind, symbolic value, resolved sRGB/system fallback color, and WordprocessingML aliases such as `text1`, `dark1`, `hyperlink`, and `followedHyperlink`.
- Exposes the palette through `metadata['docxTheme']['colors']` and `importReport['theme']`.
- Resolves theme-colored run text and run shading into reviewer metadata attributes for AST, Markdown, and WordPress block handoff while keeping raw `themeTint`/`themeShade` values.

## Evidence

- Baseline before this patch:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 3873 assertions, 0 failures`
- Final focused verification:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 3918 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-theme-color-handoff.php --self-test`
  - `wordpress-docx-theme-color-handoff self-test passed`
- Focused delta:
  - `phpPass`: `2305 -> 2306`
  - `benchmarkDenominator.mapped`: `2705 -> 2706`
  - `mappedDocxOpenXmlCoreCases`: `33 -> 34`
  - `docxOpenXmlCoreAssertions`: `385 -> 430`
  - New focused assertions: `+45`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP ZIP/OPC package reader, OPC relationship graph, DOM-based DOCX theme parsing, Pandoc-like AST, Markdown writer, WordPress block writer, focused lane test runner, and an in-memory WordPress handoff example. Full upstream Pandoc DOCX runner parity remains an upstream-runner dependency and was not attempted.

## Non-Overlap

This does not repeat accepted DOCX theme font resolution, chart style/color-style metadata, chart title/series/axis/plot metadata, embedded workbook provenance, content controls, comments/endnotes, tracked formatting revisions, DrawingML geometry, image handling, table geometry, settings variables, or legacy DOC/ODF/EPUB work. The new behavior is limited to DOCX theme color scheme parsing and theme-colored run/shading reviewer metadata.

## Exclusions

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, tar, gzip, lz4, TeX/PDF engine, Typst, browser renderer, online service, live provider test, or live-service provider test was run.

## Next

Next DOCX/OpenXML work should target a non-overlapping bounded reader gap such as table/numbering style interactions, chart theme/style inheritance, or richer DrawingML text properties.
