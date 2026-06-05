# pandoc-docx-openxml-core-current-base-20260605T075735Z

Accepted base: `6bf66e4eb549e893548d86a7960f7cf19c5eeeba`

## Behavior

Added bounded native DOCX/OpenXML run metadata support for `w:rPr/w:lang` and `w:rPr/w:rtl`.

The reader now wraps visible run content in the existing span metadata path when WordprocessingML carries:

- `w:lang/@w:val` as `lang` plus `data-docx-lang`;
- `w:lang/@w:bidi` as `data-docx-lang-bidi`;
- `w:lang/@w:eastAsia` as `data-docx-lang-east-asia`;
- enabled `w:rtl` as `dir="rtl"` plus `docx-rtl`.

The wrapper composes with existing run style and reviewer markup spans, so multilingual review text remains visible in Markdown and WordPress output without losing bold/italic/highlight/shading behavior.

## Source Truth And Non-Overlap

Source truth is the existing DOCX/OpenXML reader contract in `lanes/pandoc/src/DocxReader.php` and the pinned Pandoc static inventory for DOCX reader support-library parity. This slice is intentionally limited to run language/direction metadata and does not overlap the accepted DOCX body/properties/styles/numbering/media, OMML, tracked-change, comment-range, bookmark, field-code hyperlink, section-property, altChunk, VML textbox/image, chart/diagram placeholder, custom XML, smart tag, or content-control slices.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
  `No syntax errors detected in lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  `No syntax errors detected in lanes/pandoc/tests/DocxReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  `No syntax errors detected in lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  `1 test files, 845 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  `docx body handoff self-test ok`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  `lanes/pandoc/lane-status.json ok`
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok`
- `git diff --check -- lanes/pandoc`
  no output

The focused test delta is one new PASS case and 32 new assertions, raising the focused DOCX reader count from 813 to 845 assertions.

## Dependency Closure

No new support component is required. This reuses the existing native XML/OPC/DOCX reader, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` span attribute paths.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external office tooling, browser renderer, online sanitizer, or online service was executed. Full upstream runner parity remains gated on hydrating the pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`, `pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep paragraph-level bidi, theme font inheritance, structured drawing text extraction, SmartArt/chart semantic extraction, embedded OLE/package relationships, tracked formatting changes, footnote/endnote custom mark metadata, glossary/document settings, and broader run property inheritance as separate bounded DOCX/OpenXML slices.

Root harness: not run - isolated micro-slice.
