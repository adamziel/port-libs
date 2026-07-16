# Pandoc Doctemplates Core Current Base 2026-06-09T041826Z

## Source Truth

- Upstream doctemplates `Parser.hs` at tag `0.11.0.1` supports `left`, `right`, and `center` block pipes through the DocLayout block path: `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Parser.hs`.
- Upstream `test/pad.test` composes adjacent block pipes horizontally, including `$sup/right 15$$sup/center 15$$sup/left 15$`: `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/test/pad.test`.
- The PHP renderer already had bounded block-pipe width, Unicode padding, nesting, and border behavior. This slice keeps that support but preserves block-pipe shape until adjacent block outputs can be composed side by side.

## Patch

- Added an internal `DocTemplateBlockOutput` representation so `left`/`right`/`center` pipes can carry line lists and blank filler lines through render dispatch.
- Added block-aware append composition for adjacent block outputs. Uneven-height compositions use each block's precomputed blank filler line, preserving borders such as table separators.
- Kept explicit and automatic nesting compatibility by expanding internal block markers back to ordinary multiline text before nesting.
- Added focused `DocTemplateTest.php` coverage for the upstream `pad.test` adjacent block-pipe shape and for reviewer-table filler rows.
- Extended the WordPress doctemplate review-packet example self-test with adjacent block-pipe reviewer columns.

## Verification

- Baseline before patch: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 1133 assertions, 0 failures`.
- Focused after patch: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 1135 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` -> `OK wordpress doctemplate review packet`.
- PHP syntax checks run for changed PHP files.
- Root harness not run: isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `DocTemplate` parsing/rendering and existing `UnicodeText` display-width padding. It does not shell out to Pandoc, doctemplates Haskell test binaries, Cabal, Word, LibreOffice, zip/unzip, TeX/PDF engines, browser renderers, online services, or live providers.

## Non-Overlap

This does not repeat the accepted doctemplate work for parameterized pipes, applied partial newline preservation, nested wrapping, resource/default templates, or partial filesystem loading. It owns only adjacent block-pipe horizontal composition and bounded blank filler lines for uneven composed blocks.
