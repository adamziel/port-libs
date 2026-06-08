# DOCX/OpenXML Document Background Handoff

Slice: `pandoc-docx-openxml-core-current-base-20260608T042040Z`
Base: `e8c43317726abb932805c171a399c58fb2c01c99`

## Behavior

This slice adds bounded native DOCX/OpenXML handling for document-level
`w:background` metadata. `DocxReader` now preserves:

- root `w:background` color, themeColor, themeTint, and themeShade;
- a CSS-safe `#RRGGBB` color when the source color is a six-digit hex value;
- VML background id/style and `v:fill` title/type/color2/recolor/opacity;
- the background fill image relationship id, relationship type, target,
  target part, content type, external/internal state, existence, byte count,
  and relationship issues.

The background packet is exposed on the document AST as `docxBackground`, copied
to package metadata as `docxBackground`, and included in the DOCX import report
as `background`. Markdown and WordPress body rendering remain body-only; review
metadata is not emitted as visible content.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  failed as expected with `1 test files, 2353 assertions, 1 failures` because
  `docxBackground` was missing.
- Focused: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed with `1 test files, 2377 assertions, 0 failures`.
- Lint: `php -l lanes/pandoc/src/DocxReader.php` passed.
- Lint: `php -l lanes/pandoc/tests/DocxReaderTest.php` passed.
- Lint: `php -l lanes/pandoc/examples/wordpress-docx-background-handoff.php`
  passed.
- Example: `php lanes/pandoc/examples/wordpress-docx-background-handoff.php --self-test`
  passed.
- Lane whitespace: `git diff --check -- lanes/pandoc` passed.
- Status JSON: `lanes/pandoc/lane-status.json` parsed successfully.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The implementation reuses native
`ZipPackage`, OPC relationship resolution, WordprocessingML/VML DOM parsing,
`AstNode` metadata, and the existing Markdown/WordPress writers. No Pandoc,
Word, LibreOffice, zip/unzip, Cabal/Haskell runner, external converter, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This is limited to document root background metadata. It does not repeat existing
DOCX body, styles, numbering, table, media drawing, VML picture, chart/diagram,
comments/endnotes, tracked-revision, settings, theme, glossary, altChunk,
embedded-object, section, or table-geometry coverage.

## Follow-Up

Useful follow-ups are theme color RGB resolution for backgrounds, an explicit
WordPress review rendering policy for page backgrounds, and broader DrawingML
text-body extraction. Those are intentionally outside this slice.
