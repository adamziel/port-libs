# DOCX/OpenXML DrawingML Image Hyperlinks

Slice: `pandoc-docx-openxml-core-current-base-20260608T233318Z`
Base: `9eb676a5cd9add619cf3b6f2435447962ecbfb04`

## Behavior

- `DocxReader` now detects DrawingML `a:hlinkClick` children on `wp:docPr` for image drawings.
- Safe hyperlink relationships wrap the existing image AST node in a `link` node, preserving image media metadata while exposing click tooltip/action/history/highlight metadata as reviewer attributes.
- `a:hlinkHover` relationships are preserved as prefixed reviewer data attributes on the link handoff.
- Unsafe external drawing hyperlink targets, such as `javascript:` relationships, are rejected through the existing OPC external-target preflight and are not rendered to Markdown or WordPress blocks.
- The WordPress smoke covers linked image HTML, nested image output, hover metadata, and unsafe-target suppression.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3288 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3344 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-drawing-hyperlink-handoff.php --self-test` passed.
- Syntax checks passed for `DocxReader.php`, `DocxReaderTest.php`, and `wordpress-docx-drawing-hyperlink-handoff.php`.
- `git diff --check -- lanes/pandoc` passed.

## Delta

- `phpPass`: `1973 -> 1974`.
- Focused DOCX assertions: `3288 -> 3344` (`+56`).
- One new focused DOCX/OpenXML behavior case was added.

## Dependency Closure

No new support component is needed. This reuses native `DocxReader`, `OpcRelationships`, `OpcRelationship` external-target preflight, `ZipPackage`, `MarkdownWriter`, `WordPressBlockWriter`, and the existing TestRunner. No Pandoc, Word, LibreOffice, zip/unzip, external office tool, template engine, TeX/PDF engine, Haskell runner, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX direct `w:hyperlink` metadata, field-code hyperlinks, DrawingML geometry/docPr metadata, linked media target preflight, VML image parsing, captions, embedded object/package placeholders, subdocuments, settings, glossary relationships, or content-control binding work. It only closes the DrawingML image hyperlink handoff for `a:hlinkClick` / `a:hlinkHover` metadata attached to `wp:docPr`.

## Next

Good non-overlapping DOCX/OpenXML follow-ups remain additional DrawingML picture nonvisual metadata, theme font inheritance edges, SEQ/caption numbering heuristics, or table/list conversion gaps.
