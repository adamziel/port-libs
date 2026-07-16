# ODF/OpenDocument Core Current-Base Slice - 2026-06-07

Micro-slice: `pandoc-odf-open-document-core-current-base-20260607T022758Z`
Accepted base: `dceb129b94af76d8e90cb1d4f15360a8db272ff2`

## Behavior

`OdfReader` now applies resolved paragraph style `style:text-properties` to non-preformatted paragraph inline content.

- Paragraph styles with bold/italic/underline/strikeout/small-caps/superscript/subscript modifiers wrap inline children through the same native AST nodes used by `text:span`.
- The paragraph `styleName` remains visible as a `span` with `data-odf-style-name` when a paragraph style contributes text modifiers.
- Inherited paragraph text properties are resolved before wrapping, so child paragraph styles can inherit parent emphasis.
- Plain paragraph styles without text modifiers keep their current unwrapped inline content.
- Preformatted paragraph styles still become code blocks before paragraph text-property wrapping, preserving the existing source-code path.

Source truth: pinned Pandoc ODT `ContentReader.hs` applies `withNewStyle` to ordinary `read_paragraph` content before `constructPara`; this slice ports that bounded style-modifier contract without running Pandoc or external office converters.

## Evidence

Baseline focused check:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1341 assertions, 0 failures`

Red check after adding the focused paragraph-style case:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1344 assertions, 1 failures`
- Failure: expected the styled paragraph child to be `strong`; actual child remained `text`.

Green focused check after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1365 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
- Result: `odf open document handoff self-test ok`

Status delta:

- mapped denominator: `1852 -> 1853`
- ODF/OpenDocument core cases: `11 -> 12`
- ODF/OpenDocument core assertions: `251 -> 275`
- New focused assertions: `+24`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP ODF style parsing/resolution, AST inline wrappers, Markdown/WordPress writers, and the existing in-memory ODT package fixture helper. Full upstream Pandoc ODT runner parity remains separate and requires explicit Haskell/Cabal runner authorization.

## Non-Overlap

This avoids the accepted ODF slices for text:tab normalization, blockquote paragraph styles, heading auto identifiers, heading source IDs, conditional/hidden fields, table captions, sections, forms, fields, generated indexes, and embedded object handoffs. The slice only owns ordinary paragraph text-property modifiers before Markdown/WordPress handoff.
