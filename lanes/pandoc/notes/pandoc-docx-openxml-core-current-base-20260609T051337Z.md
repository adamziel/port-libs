# DOCX/OpenXML DrawingML text properties

Slice: `pandoc-docx-openxml-core-current-base-20260609T051337Z`
Base: `40ecdbe743809a1f1af99ee730ab306fb571c756`

## Behavior

- `DocxReader` now preserves bounded DrawingML text paragraph properties from `a:pPr` as nested reviewer spans.
- Captured paragraph metadata includes alignment, level, margins, indent, tab size, RTL/line-break flags, line/ before/after spacing, bullet character, bullet font, and auto-numbering type/start.
- `DocxReader` now preserves bounded DrawingML text run properties from `a:rPr` as nested reviewer spans.
- Captured run metadata includes bold, italic, underline, strike, capitalization, font size, language, alternate language, baseline, character spacing, no-proof/dirty flags, fill color, highlight color, and Latin/East Asian/complex-script/symbol typefaces.
- Plain DrawingML text remains plain when no `a:pPr` or `a:rPr` metadata is present. The importer still does not render Office layout, evaluate shape styles, invoke Word/LibreOffice, or shell out to Pandoc.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes existed before this slice.
- Latest prior DOCX current-base note in this worktree recorded:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  with `1 test files, 3948 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 4104 assertions, 0 failures`.
- Syntax checks:
  `php -l lanes/pandoc/src/DocxReader.php`
  passed with no syntax errors.
  `php -l lanes/pandoc/tests/DocxReaderTest.php`
  passed with no syntax errors.
  `php -l lanes/pandoc/examples/wordpress-docx-drawing-text-properties-handoff.php`
  passed with no syntax errors.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-drawing-text-properties-handoff.php --self-test`
  passed with `wordpress-docx-drawing-text-properties-handoff self-test passed`.

## Delta

- Added one mapped DOCX/OpenXML behavior case.
- Focused DOCX assertion evidence moved from the latest prior DOCX note's `3948` assertions to `4104` assertions.
- `phpPass` is intentionally unchanged because this patch expands focused assertions inside the existing DOCX test file rather than adding a new top-level TestRunner PASS case.
- `benchmarkDenominator.mapped`: `2750 -> 2751`.
- `mappedDocxOpenXmlCoreCases`: `33 -> 34`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, OPC relationship loading, DOM-based DrawingML parsing in `DocxReader`, `AstNode` metadata spans, `MarkdownWriter`, `WordPressBlockWriter`, the focused lane TestRunner, and a lane-local WordPress DOCX smoke.

Full upstream Pandoc DOCX runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX package loading, media relationship parsing, DrawingML geometry, picture nonvisual/effects/crop metadata, chart style/color/title/series/axis/plot/legend metadata, SmartArt placeholders, connector/group-shape placeholders, VML/DrawingML textbox body extraction, content controls, custom XML binding, comments/endnotes, tracked revisions, bookmarks, field-code hyperlinks, document defaults, theme color/font handoff, numbering style links, table geometry/metadata, embedded objects, subdocuments, or OPC relationship preflight work.

This slice is limited to richer DrawingML `a:txBody` paragraph and run property metadata on visible shape text.

## Next

Good non-overlapping DOCX/OpenXML follow-ups include latent style defaults, chart theme/style inheritance, table/numbering style interactions beyond accepted style links, or additional non-text DrawingML metadata that is not already covered by geometry, picture, connector, group-shape, chart, or text-property handoffs.
