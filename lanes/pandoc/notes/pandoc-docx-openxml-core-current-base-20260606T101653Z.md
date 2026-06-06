# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260606T101653Z`
Base accepted HEAD: `e1661ddde6bf69323245293250d294a721f7503c`

## Behavior Added

- `DocxReader` now preserves bounded WordprocessingML paragraph tab-stop definitions from `w:pPr/w:tabs/w:tab`.
- Paragraph reviewer spans now expose deterministic metadata for each usable tab stop:
  - `docx-paragraph-tabs`
  - `data-docx-tab-stop-count`
  - `data-docx-tab-N-val`
  - `data-docx-tab-N-pos-twips`
  - `data-docx-tab-N-leader`
- The WordPress DOCX body handoff example now includes paragraph-level tab-stop metadata in its self-test fixture.

## Source Truth

- The bounded OpenXML contract for this slice is `w:pPr/w:tabs/w:tab` with `w:val`, `w:pos`, and optional `w:leader` attributes.
- This preserves authoring/layout provenance for review without trying to reproduce Word layout measurement, default-tab-stop expansion, or renderer-specific tab positioning.
- No hydrated local Pandoc checkout was present in `/home/claude/port-libs/.upstream-cache/pandoc`, so this slice used the stable WordprocessingML element contract and the existing native PHP DOCX fixtures rather than running upstream Haskell tests.

## Verification

- Red-first focused check after adding expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1655 assertions, 1 failures`
  - Failure: `docx-paragraph-tabs` was absent because `w:tabs` metadata was ignored.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1703 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1709 -> 1710`.
- DOCX/OpenXML mapped cases: `32 -> 33`.
- DOCX/OpenXML focused assertion inventory: `357 -> 366`.
- Added `mappedDocxParagraphTabStopCases: 1` and `docxParagraphTabStopAssertions: 9`.
- `lanes/pandoc/lane-status.json` `phpPass`: unchanged at `1295`; this strengthens an existing focused `DocxReaderTest.php` PASS case instead of adding a new TestRunner case.

## Non-Overlap

This does not repeat accepted DOCX/OpenXML work for run-level `w:ptab` positional tabs, ordinary run `w:tab`, page/column/rendered breaks, paragraph alignment/spacing/indent/bidi/textDirection metadata, style and numbering resolution, media, VML/DrawingML images, charts/diagrams, embedded objects, comments, notes, bookmarks, fields, content controls, smart tags, custom XML, glossary parts, tracked changes, OMML math, altChunk, settings, section/header/footer metadata, theme fonts, run color, highlight, shading, or OPC relationship preflight. It owns only paragraph-level tab-stop definition metadata.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, OPC package helpers, DOM parsing, `DocxReader` paragraph metadata, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter`. No Pandoc, Cabal build/test, Haskell runner, Word, LibreOffice, zip/unzip, external office tooling, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Keep exact Word tab layout measurement, style-inherited/default tab-stop interaction refinements, and full upstream Pandoc Haskell runner parity as separate bounded DOCX/OpenXML slices.
