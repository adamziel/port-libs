# pandoc-docx-openxml-core-current-base-20260605T083055Z

Accepted base: `c95d42ada7c1e699c25b1acfd56c5ce5fa8279d5`

## Behavior

Added bounded native DOCX/OpenXML paragraph bidi and text-direction metadata
support in `DocxReader`.

- Enabled `w:pPr/w:bidi` now wraps the paragraph or heading inline content in a
  reviewer span with `docx-paragraph-bidi`, `docx-rtl`,
  `data-docx-paragraph-bidi="true"`, and `dir="rtl"`.
- `w:pPr/w:textDirection` is preserved as `docx-text-direction`,
  a value-specific class, and `data-docx-text-direction`.
- Disabled paragraph bidi values such as `w:val="0"` stay plain and do not
  produce direction metadata.

## Source Truth And Non-Overlap

This is a bounded WordprocessingML paragraph-property behavior needed by DOCX
body import. It extends the accepted run-level language/RTL metadata path
without changing run properties, styles, numbering, media, OMML, tracked
changes, comments, bookmarks, field-code hyperlinks, section geometry,
altChunk, VML textbox/image, chart/diagram placeholders, custom XML, smart
tags, content controls, OPC package parsing, or relationship preflight.

## Verification

- Baseline before edit:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 845 assertions, 0 failures`
- Focused after edit:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 878 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- JSON validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
- Whitespace:
  - `git diff --check -- lanes/pandoc`

The focused delta is one new DOCX/OpenXML PASS case and `+33` focused
assertions, raising `DocxReaderTest.php` from `31 PASS / 845 assertions` to
`32 PASS / 878 assertions`.

## Dependency Closure

No new support component is required. This reuses the existing native XML,
DOCX reader, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` span
attribute paths.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external
office tooling, browser renderer, online sanitizer, or online service was
executed. Full upstream runner parity remains gated on hydrating the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
`cabal.project`, `pandoc.cabal`, and
`pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep theme font inheritance, structured drawing text extraction, SmartArt/chart
semantic extraction, embedded OLE/package relationships, tracked formatting
changes, footnote/endnote custom mark metadata, glossary/document settings,
broader run property inheritance, and DocxReader surfacing of package
consistency diagnostics as separate bounded slices.

Root harness: not run - isolated micro-slice.
