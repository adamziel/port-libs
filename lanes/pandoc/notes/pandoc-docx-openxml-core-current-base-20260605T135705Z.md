# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T135705Z`
Base accepted HEAD: `c34e7a9869de48d529549b9310dbbd41207f3e1d`

## Behavior Added

- `DocxReader` now classifies WordprocessingML `w:br` run breaks.
- Default and `textWrapping` breaks continue to produce regular linebreak
  AST nodes.
- `w:br w:type="page"` and `w:br w:type="column"` now produce visible reviewer
  span nodes with `data-docx-break-type` metadata.
- `w:clear` values on page/column breaks are preserved as
  `data-docx-break-clear` plus a `docx-break-clear` class.
- The WordPress DOCX body handoff smoke now exposes page and column layout
  checkpoints in rendered block output.

## Source Truth And Non-Overlap

DOCX run breaks are WordprocessingML body/run content. This slice stays bounded
to `w:br` page/column/text-wrapping semantics and does not reinterpret section
page geometry, paragraph `w:pageBreakBefore`, headers/footers, field codes,
tracked changes, comments, bookmarks, media, VML, DrawingML, altChunk, OMML,
numbering, table spans, or style inheritance.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, `zip`, `unzip`,
`ZipArchive`, external office tooling, browser renderer, online sanitizer, or
online service was executed.

## Verification

- Baseline focused DOCX test before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1233 assertions, 0 failures`
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1254 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`

Focused delta: one new DOCX/OpenXML PHP PASS case and `+21` focused assertions.

## Status Delta

- `lane-status.json` `phpPass`: `935` -> `936`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1391` ->
  `1392`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `334`.
- Added `mappedDocxBreakRunCases: 1` and `docxBreakRunAssertions: 21`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
ZIP/OPC package reader, `DocxReader`, `MarkdownWriter`, and
`WordPressBlockWriter`.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep theme font inheritance, tracked formatting-change metadata, glossary
document parts, commentsExt metadata, richer drawing text extraction, and full
upstream Pandoc Haskell runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
