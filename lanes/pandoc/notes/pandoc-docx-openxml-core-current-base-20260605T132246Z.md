# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T132246Z`
Base accepted HEAD: `63ef80057b8d1de0508797d5d478036f38041bd9`

## Behavior Added

- `DocxReader` now loads bounded `w:style w:type="character"` entries from
  `word/styles.xml` instead of discarding non-paragraph styles.
- `w:rPr/w:rStyle` now resolves inherited run properties through `w:basedOn`
  character-style chains before applying direct run properties.
- Inherited bold, italic, underline, strike, vertical-align, small-caps,
  highlight, shading, language, and RTL metadata now flow into the existing AST
  inline nodes and WordPress spans.
- Direct run properties override inherited character-style metadata, including
  disabled inherited italic and `w:highlight w:val="none"`.
- The WordPress DOCX body handoff example now self-tests character-style
  reviewer labels in rendered block output.

## Source Truth And Non-Overlap

This is a bounded WordprocessingML style contract: character styles are stored
as `w:style w:type="character"` in `word/styles.xml`, selected by
`w:rPr/w:rStyle`, and chained by `w:basedOn`.

This patch does not repeat accepted DOCX/OpenXML work for ZIP/OPC package
loading, paragraph-style layout metadata, paragraph/list styles, numbering,
table captions/descriptions, media, VML, drawings, charts, diagrams, embedded
OLE/package placeholders, comments, comment ranges, bookmarks, field-code
hyperlinks, tracked insert/delete/move rendering, content controls, smart tags,
custom XML, OMML formulas, altChunk import, document settings, section
geometry, headers/footers, note policy, or missing note placeholders.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external
office tooling, browser renderer, online sanitizer, or online service was
executed.

## Verification

- First focused DOCX run after adding the fixture and implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1229 assertions, 1 failures`
  - Failure: the new character-style behavior was present, but the fixture
    expected underline as a span while the existing WordPress writer emits
    `<u>` for underline nodes.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1233 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
- Whitespace:
  - `git diff --check -- lanes/pandoc`

Focused delta: one new DOCX/OpenXML mapped PHP PASS case and `+42` focused
DOCX assertions, raising `DocxReaderTest.php` from `1191` to `1233`
assertions.

## Status Delta

- `lane-status.json` `phpPass`: `918` -> `919`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1375` ->
  `1376`.
- `docxOpenXmlCoreCases`: `31` -> `32`.
- `mappedDocxOpenXmlCoreCases`: `31` -> `32`.
- `docxOpenXmlCoreAssertions`: `313` -> `355`.
- Added `mappedDocxCharacterStyleRunCases: 1` and
  `docxCharacterStyleRunAssertions: 42`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
ZIP/OPC package reader, XML DOM helpers, `DocxReader`, `MarkdownWriter`, and
`WordPressBlockWriter`.

Full upstream runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal`.

## Follow-Up

Keep theme font inheritance, tracked formatting-change metadata, glossary
document parts, drawing text extraction, commentsExt metadata, and full
upstream Pandoc Haskell runner parity as separate bounded slices.

Root harness: not run - isolated micro-slice.
