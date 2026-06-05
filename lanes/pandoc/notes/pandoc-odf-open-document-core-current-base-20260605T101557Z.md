# Pandoc ODF/OpenDocument Core Current Base - 2026-06-05T10:15:57Z

Slice: `pandoc-odf-open-document-core-current-base-20260605T101557Z`

Base accepted HEAD: `a63d0e111d11d4cbd43704afd1c4614546f1110e`

## Behavior

- Added native `text:soft-page-break` handling to `OdfReader`.
- The reader now emits a zero-width inline `span` with class `odf-soft-page-break` and `data-odf-soft-page-break="true"` so WordPress review packets can retain source page-boundary hints without changing paragraph text.
- `importReport.content.softPageBreakCount` now recursively counts those markers.
- Updated the ODF WordPress handoff example to include and self-test the soft page-break marker.

## Source Truth And Non-Overlap

- Source truth was the pinned Pandoc ODT reader shape at `jgm/pandoc` `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, especially the core inline matcher around OpenDocument text-space, line-break, tab, link, citation, bookmark, reference, and frame handling.
- This does not overlap the accepted ODF link-metadata, sequence, bibliography-mark, list-continuation, annotation-range, tracked-change, embedded-object, table, section, style, manifest, or media slices.
- No Pandoc binary, Haskell runner, LibreOffice, office tools, zip/unzip, or online conversion service was executed.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 612 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`
- `php -l lanes/pandoc/src/OdfReader.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - Result: no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`

## Dependency Closure

No new support component is needed. This reuses the existing native ODF XML reader plus shared `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` span-attribute rendering paths.

Root harness: not run - isolated micro-slice.
