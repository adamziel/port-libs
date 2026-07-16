# Pandoc ODF Source Text Inline Code Handoff

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260606T021617Z`
- Accepted base: `3d74120a9c1b8d588cf826b675c1e5e30d4592e7`
- Upstream source truth: pinned Pandoc `ContentReader.hs` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`; `read_span` applies `withNewStyle`, and `withNewStyle` maps `Source_Text` and `Source_20_Text` through the inline-code branch (`inlineCode = code . T.concat . map stringify . toList`).

## Behavior

- `OdfReader` now maps `text:span` styles `Source_Text` and `Source_20_Text` into AST `code` inline nodes instead of generic styled spans.
- The source style name is preserved as `styleName` and `data-odf-style-name` metadata for Markdown and WordPress review output.
- Paragraph and heading plain-text metadata still includes inline code text, so import summaries do not drop source helper names.
- The existing WordPress ODF handoff example now includes a `Source_Text` helper span and self-tests the rendered `<code>` output.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1070 assertions, 0 failures`.
- Red-first: after adding the focused case, `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed with `1 test files, 1072 assertions, 1 failures` because `Source_Text` emitted a generic `span` node instead of `code`.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1084 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed with `odf open document handoff self-test ok`.

## Status Delta

- `lane-status.json` `phpPass`: `1151 -> 1152`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1601 -> 1602`.
- ODF/OpenDocument core cases: `10 -> 11`.
- ODF/OpenDocument focused assertions: `217 -> 231`.
- Focused `OdfReaderTest.php` coverage: `44 -> 45` PASS cases and `1070 -> 1084` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `OdfReader`, `ZipPackage` fixture support, shared `AstNode` model, `MarkdownWriter`, `WordPressBlockWriter`, and the existing ODF WordPress handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, stack, Word, LibreOffice, zip/unzip, external converter, online sanitizer, online service, or live provider test was executed.

## Non-Overlap And Follow-Up

This slice is separate from recent ODF clusters for `text:tab` normalization, paragraph blockquote style mapping, parent-relative links, placeholders, table-template metadata, MathML objects, generated indexes, bibliography marks, sections, annotations, frame images, and form controls. Follow-up ODF work should keep richer source-language metadata, broader style-chain code inheritance beyond the two pinned source-text style names, export-side ODT writing, and full upstream Haskell runner parity as separate bounded slices.
