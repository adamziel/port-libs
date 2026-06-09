# pandoc-docx-openxml-core-current-base-20260609T044330Z

Accepted base: `b7207ea8e728f24041eefd971a1a50d4e50c22fc`

## Scope

- Implemented bounded native DOCX/OpenXML numbering-level paragraph-style link support.
- `DocxReader` now preserves `w:lvl/w:pStyle` from `word/numbering.xml`.
- Paragraphs whose style or based-on style is linked from a numbering level now resolve into AST lists even when the paragraph style has no direct `w:numPr`.
- Explicit direct `w:numPr/w:numId w:val="0"` still suppresses numbering and leaves visible paragraph text.
- The handoff flows through the existing Markdown and WordPress block writers, so Word-authored reviewer checklist styles become nested WordPress lists without office tooling.

## Evidence

- Red-first focused test after adding expectations and before production changes:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 3921 assertions, 1 failures`.
  - Failure: style-linked list paragraphs remained seven plain document children instead of one list plus two paragraphs.
- Final focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 3948 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-numbering-style-link-handoff.php --self-test`
  - Result: `wordpress-docx-numbering-style-link-handoff self-test passed`.

## Delta

- `phpPass`: `2318 -> 2319`.
- `benchmarkDenominator.mapped`: `2718 -> 2719`.
- Added one mapped DOCX/OpenXML behavior case.
- Added 28 focused DOCX assertions in `DocxReaderTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, OPC relationship loading, `DocxReader` style/numbering parsing, `MarkdownWriter`, `WordPressBlockWriter`, the focused lane TestRunner, and a lane-local WordPress DOCX smoke. Full upstream Pandoc DOCX runner parity remains an upstream-runner dependency and was not attempted.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX style document defaults, paragraph/run style inheritance, direct style `numPr`, numbering level overrides, nested direct numbering, table spans/metadata, comments/endnotes, tracked revisions, bookmarks, field-code hyperlinks, DrawingML/chart metadata, content controls, OPC preflight, ODT/EPUB/PDF/legacy-DOC work, or archive/charset/math/doctemplate/citation support slices. The patch is limited to numbering-level paragraph-style link resolution via `w:lvl/w:pStyle`.

## Next

A next DOCX/OpenXML slice could cover table style inheritance, latent style defaults, chart theme inheritance, or richer DrawingML text properties without repeating this numbering style-link behavior.
