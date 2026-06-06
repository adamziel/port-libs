# Pandoc Table Geometry Caption Writer Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T015503Z`
Base accepted HEAD: `a5143ad2fb20bad3e1fc096fc06844256ce0edb3`

## Behavior

- Added native PHP table-geometry writer diagnostics for table captions that require writer-specific handling:
  - Markdown short captions now report `markdown-short-caption-prefix-required` with `pandoc-short-caption-prefix`.
  - Markdown block captions now report `markdown-caption-blocks-flattened` with `plain-caption-text`.
  - AsciiDoc short captions now report `asciidoc-short-caption-review-required` with `table-short-title-review`.
  - AsciiDoc block captions now report `asciidoc-caption-blocks-flattened` with `plain-caption-text`.
  - LaTeX short captions now report `latex-short-caption-optional-argument-required` with `caption-optional-argument`.
  - LaTeX block captions now report `latex-caption-blocks-flattened` with `caption-text`.
- The diagnostics are emitted by direct `TableGeometry::writerDowngradeDiagnostics()` calls and by `TableGeometry::reviewPacket()` writer summaries. Existing WordPress rendering still preserves `data-pandoc-short-caption` and block-level `<figcaption>` content.

## Evidence

- Red-first focused run before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: failed the new caption writer test because Markdown writer diagnostics returned no caption-specific records.
- Focused green runs after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 791 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 1105 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`.
- Syntax checks:
  - `php -l lanes/pandoc/src/TableGeometry.php`
  - `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TableGeometry`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` paths. No Pandoc, Cabal solver/build/test command, Haskell runner, external writer, Word, LibreOffice, zip/unzip, browser renderer, online sanitizer, online service, or live provider test was run.

## Follow-Up

Keep full HTML5 table algorithm parity, richer writer-specific caption rendering, target-specific block-cell downgrade rules, and broader WordPress/table accessibility review cases as separate bounded slices.

Root harness: not run - isolated micro-slice.
