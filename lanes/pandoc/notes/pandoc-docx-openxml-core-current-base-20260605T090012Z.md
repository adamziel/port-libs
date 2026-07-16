# pandoc-docx-openxml-core-current-base-20260605T090012Z

Accepted base: `dee38a517ce5ee272eef5f61b93a5a54e201fd7b`

## Behavior

Added bounded native DOCX/OpenXML paragraph layout metadata preservation in
`DocxReader`.

- `w:pPr/w:jc` now wraps paragraph or heading inline content in a reviewer
  span with `docx-paragraph-align`, a value-specific `docx-align-*` class, and
  `data-docx-paragraph-align`.
- `w:pPr/w:spacing` preserves before/after twips, raw line value, line rule,
  and before/after line counts as `data-docx-spacing-*` attributes.
- `w:pPr/w:ind` preserves left/right/start/end/firstLine/hanging indentation
  twips as `data-docx-indent-*` attributes.
- Enabled `w:keepNext` and `w:pageBreakBefore` now surface as reviewer
  metadata; disabled values such as `w:val="0"` or `w:val="false"` stay plain.

## Source Truth And Non-Overlap

This is a bounded WordprocessingML paragraph-property behavior needed by DOCX
body import and WordPress review packets. It extends the accepted paragraph
bidi/text-direction metadata path without changing run properties, run
language/RTL, styles, numbering, media, OMML, tracked changes, comments,
bookmarks, field-code hyperlinks, section geometry, altChunk, VML
textbox/image, chart/diagram placeholders, custom XML, smart tags, content
controls, OPC package parsing, or relationship preflight.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external
office tooling, browser renderer, online sanitizer, or online service was
executed.

## Verification

- Red-first focused test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 880 assertions, 1 failures`
  - Failure: the new paragraph-layout case saw a plain `text` node instead of
    a reviewer metadata `span`.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 920 assertions, 0 failures`
- Focused lane directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - `20 test files, 9480 assertions, 0 failures`
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - `775`
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

Focused delta: one new DOCX/OpenXML PASS case and `+42` focused DOCX
assertions, raising `DocxReaderTest.php` from `32 PASS / 878 assertions` to
`33 PASS / 920 assertions`.

## Dependency Closure

No new support component is required. This reuses the existing native XML/OPC
DOCX reader, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` span
attribute paths.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep style-inherited paragraph layout metadata, table captions/descriptions,
drawing text extraction, tracked formatting changes, footnote/endnote custom
mark metadata, glossary/document settings, embedded OLE/package relationships,
and fuller upstream DocxReader parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
