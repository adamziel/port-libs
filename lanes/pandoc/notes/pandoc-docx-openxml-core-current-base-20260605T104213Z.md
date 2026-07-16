# pandoc-docx-openxml-core-current-base-20260605T104213Z

Accepted base: `c6b8bdd91e9129ca076584776bb76e4fcded4d0c`

## Behavior

Added bounded native DOCX/OpenXML paragraph style-chain layout metadata
preservation in `DocxReader`.

- `word/styles.xml` paragraph styles now retain bounded `w:pPr` reviewer
  layout metadata for `w:jc`, `w:spacing`, `w:ind`, `w:keepNext`, and
  `w:pageBreakBefore`.
- Paragraphs that only carry `w:pPr/w:pStyle` now inherit those metadata spans
  through the style `w:basedOn` chain.
- Direct paragraph layout properties merge on top of inherited style metadata,
  including direct alignment overriding the inherited alignment-specific class.
- The inherited metadata survives both Markdown attribute output and WordPress
  block span output.

## Source Truth And Non-Overlap

Pandoc's DOCX reader treats paragraph styles from `word/styles.xml` as part of
WordprocessingML paragraph interpretation, not as a separate office-tool pass.
This slice keeps that contract bounded to reviewer layout metadata already
supported for direct paragraph properties.

This does not repeat accepted DOCX package loading, OPC relationship preflight,
styles/numbering heading and list mapping, direct paragraph layout metadata,
run language/RTL metadata, run highlighting/shading, comments/endnotes,
tracked changes, bookmarks, field-code hyperlinks, section properties,
altChunk, VML textbox/image, chart/diagram placeholders, embedded OLE/package
placeholders, settings, OMML math, or table span parsing.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external
office tooling, browser renderer, online sanitizer, or online service was
executed.

## Verification

- Existing committed focused test case count:
  - `git show HEAD:lanes/pandoc/tests/DocxReaderTest.php | rg -c "=> static function"`
  - Result: `35`
- Patched focused test case count:
  - `rg -c "=> static function" lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `36`
- New focused assertion count:
  - `awk '/preserves DOCX paragraph style inherited layout metadata as reviewer spans/{flag=1} flag{print} /preserves nested DOCX numbering levels as child AST lists/{flag=0}' lanes/pandoc/tests/DocxReaderTest.php | rg -c '\\$t->'`
  - Result: `28`
- Focused DOCX test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1065 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- JSON validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: `lanes/pandoc/lane-status.json ok`; `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok`
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: no output.

Focused delta: one new DOCX/OpenXML mapped PHP PASS case and `+28` focused
assertions for the new paragraph style metadata case. `DocxReaderTest.php`
passes at `36` focused cases and `1065` assertions.

## Dependency Closure

No new support component is required. This reuses the existing native PHP
XML/OPC DOCX reader, style loader, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` span attribute paths.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep character style run-property inheritance, table captions/descriptions,
tracked formatting changes, glossary document parts, drawing text extraction,
and fuller upstream DocxReader parity as separate bounded DOCX/OpenXML slices.

Root harness: not run - isolated micro-slice.
