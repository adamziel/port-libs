# ODF OpenDocument Measure Field Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260608T223109Z`
Base accepted HEAD: `a93e698ac06f7885c2a47509237e09731628d097`

## Behavior

`OdfReader` now treats `text:measure` as a bounded OpenDocument text field instead of dropping it from inline paragraph content. The handoff preserves visible fallback text plus `text:name`, `text:kind`, `text:formula`, `office:value-type`, `office:value`, `office:currency`, and `style:data-style-name` metadata as inert `odf-field odf-field-measure` spans for Markdown and WordPress review output.

This is native PHP support-library work only. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Evidence

- Baseline focused test before this patch: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2453 assertions, 0 failures`.
- Red-first focused test after adding the measure-field expectation failed with `1 test files, 2454 assertions, 1 failures` because the paragraph rendered `Measures  and fallback  stay reviewable.`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2479 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed.

## Non-Overlap

This slice does not repeat the accepted ODF drop-down, conditional/hidden text field, source metadata field, inline meta span, database field/range, data-pilot, named-expression, tracked-table-change, draw-layer, link event-listener, image anchor, chart metadata, or heading source-id clusters. It is limited to `text:measure` field preservation in the ODF content XML mapping path.

## Dependency Closure

No new support component is needed. The patch reuses native `OdfReader` field-node construction, `MarkdownWriter`, `WordPressBlockWriter`, and the existing in-memory ODT package fixture path. The upstream Pandoc checkout is not present in the local upstream cache for this lane, so source truth remains the accepted ODF support-library contract and the red/green PHP behavior evidence.

## Next Task

Choose a non-overlapping ODF content mapping surface such as sender metadata fields, data-validity input-message metadata, tracked table dependency metadata, or drawing caption/anchor metadata not already covered.
