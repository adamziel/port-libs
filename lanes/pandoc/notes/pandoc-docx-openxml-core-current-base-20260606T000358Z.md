# DOCX/OpenXML Theme Font Handoff

Slice: `pandoc-docx-openxml-core-current-base-20260606T000358Z`
Base accepted HEAD: `28606f4902d1e0ce5d9990f328dc9321ece6979b`

## Behavior

- Added bounded native DOCX theme relationship support in `DocxReader`.
- The reader now loads the document theme part, records the theme font scheme
  name, and exposes major/minor Latin, East Asian, and complex-script font
  slots in both `metadata.docxTheme` and `importReport.theme`.
- `w:rFonts` direct attributes and theme slots now flow through run property
  style chains into reviewer spans, with resolved attributes such as
  `data-docx-font-ascii` plus source slots such as
  `data-docx-theme-font-ascii`.
- Direct run font values override inherited theme font slots for the run while
  still preserving explicit theme slots for other scripts.
- The WordPress DOCX body handoff smoke now includes the same theme-font
  metadata in reviewer block output.

## Source Truth And Non-Overlap

The bounded OpenXML contract for this slice is the document relationship type
`http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme`,
DrawingML `a:theme/a:themeElements/a:fontScheme`, and WordprocessingML
`w:rFonts` `asciiTheme`, `hAnsiTheme`, `eastAsiaTheme`, and `cstheme`
attributes. This preserves source typography provenance for review without
attempting full Word font fallback, theme color resolution, or renderer layout.

This does not repeat accepted DOCX package loading, OPC relationship graph
preflight, styles/numbering/list handling, media/VML/DrawingML images,
chart/diagram placeholders, embedded objects, footnotes/endnotes/comments,
comment ranges, note markers, bookmarks, field-code hyperlinks, proof and
permission ranges, content controls, smart tags, custom XML, tracked
insert/delete/move/formatting revisions, OMML math, altChunk import, settings,
document variables, glossary parsing, section/header/footer metadata, symbols,
ruby, run language/RTL, paragraph bidi/layout, page/column/rendered page
breaks, drawing text boxes, ZIP payload validation, ODT/EPUB/PDF/math/table
geometry, or upstream-runner dependency audit work.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, browser renderer, online sanitizer, online
service, or live provider test was executed.

## Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1563 assertions, 0 failures
```

Focused DOCX test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 1611 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test
docx body handoff self-test ok
```

Focused delta: one new DOCX/OpenXML PHP PASS case and `+48` focused
assertions.

## Status Delta

- `lane-status.json` `phpPass`: `1115` -> `1116`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1567` ->
  `1568`.
- `docxOpenXmlCoreCases`: `32` -> `33`.
- `mappedDocxOpenXmlCoreCases`: `32` -> `33`.
- `docxOpenXmlCoreAssertions`: `357` -> `405`.
- Added `mappedDocxThemeFontCases: 1` and `docxThemeFontAssertions: 48`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
OPC relationship/content-type helpers, XML DOM helpers, `DocxReader`,
`AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
`cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present before any
non-mutating Cabal solver/build plan.

## Follow-Up

Keep theme color resolution, per-script font inheritance refinements,
theme-aware default run properties, glossary-local relationships, and fuller
Word font fallback behavior as separate bounded DOCX/OpenXML slices.

Root harness: not run - isolated micro-slice.
