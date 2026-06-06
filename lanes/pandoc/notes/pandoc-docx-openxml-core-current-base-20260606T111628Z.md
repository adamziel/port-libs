# pandoc-docx-openxml-core-current-base-20260606T111628Z

Base: `f3e6ef9e9a7803edbdb9db6d76cbe13ebbfcd147`

## Behavior Added

- `DocxReader` now handles bounded WordprocessingML move-range tracked-change markers:
  - `w:moveFromRangeStart` / `w:moveFromRangeEnd`
  - `w:moveToRangeStart` / `w:moveToRangeEnd`
- Moved-from range text is suppressed from the rendered AST and WordPress blocks, matching the accepted tracked-change policy for deleted/moved-from text.
- Moved-to range content is wrapped in a reviewer span with:
  - `.docx-move-to-range`
  - `data-docx-change="move-to-range"`
  - `data-docx-change-id`
  - `data-docx-author`
  - `data-docx-date`
  - `data-docx-move-range-name`
- The tracked-change import report now counts `move-to-range` as accepted insertion-like revision metadata and `move-from-range` as suppressed deletion-like revision metadata, preserving source text for audit.
- The WordPress DOCX body handoff example now exercises and self-tests both move-range directions.

## Source Truth

- This slice follows the stable WordprocessingML tracked-change range marker contract rather than evaluating Word revision state globally.
- The existing lane policy remains: accepted insertions/move destinations render with provenance; deleted/moved-from source text is kept in the import report but not rendered.
- No hydrated local Pandoc checkout or office tool runner was needed for this bounded support-library behavior.

## Verification

- Red-first after adding the new fixture:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1740 assertions, 1 failures`
  - Failure: moved-from and moved-to range text rendered as plain paragraph text.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1768 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1310 -> 1311`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1724 -> 1725`.
- DOCX/OpenXML mapped cases: `32 -> 33`.
- DOCX/OpenXML focused assertion inventory: `357 -> 387`.
- Added `mappedDocxMoveRangeCases: 1` and `docxMoveRangeAssertions: 30`.

## Non-Overlap

This does not repeat accepted DOCX/OpenXML work for `w:ins`, `w:del`, `w:moveTo`, `w:moveFrom`, tracked paragraph/run formatting changes, direct hyperlinks, field-code hyperlinks, cross-reference fields, bookmarks, comments, proof/permission ranges, content controls, smart tags, custom XML, symbols, ruby, paragraph tab stops, run positional tabs, page/column/rendered breaks, section metadata, media, DrawingML/VML, embedded objects, OMML, altChunk, settings, theme fonts, glossary parts, or OPC preflight. It owns only move-range tracked-change markers.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, OPC package helpers, DOM parsing, `DocxReader` tracked-change/import-report handling, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, the focused PHP test harness, and the existing DOCX body handoff example.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Keep cross-paragraph move ranges, full Word revision accept/reject policy modeling, and exact Word field/layout recalculation as separate bounded DOCX/OpenXML slices unless concrete fixtures require them.
