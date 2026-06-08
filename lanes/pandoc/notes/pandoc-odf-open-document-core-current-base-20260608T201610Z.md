# ODF/OpenDocument Default Cell Styles

Slice: `pandoc-odf-open-document-core-current-base-20260608T201610Z`
Base accepted HEAD: `94d7cef270e305ef6fc0f67053ec55d96bb371c3`

## Summary

This slice ports a bounded ODF table-style handoff gap: `table:default-cell-style-name` on rows and columns now applies to table cells without an explicit `table:style-name`. Explicit cell styles remain authoritative.

`OdfReader` now carries row and column default cell styles into table-cell parsing, records `defaultCellStyleName` and `defaultCellStyleSource`, resolves inherited style properties, and emits safe `data-odf-cell-default-style-*` review attributes for WordPress/table-geometry handoff.

## Source Truth and Scope

The local upstream cache for Pandoc is not hydrated in this worktree, so this was bounded to the accepted lane manifest and the existing native ODF reader contract for style inheritance and table metadata. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

This does not overlap the recent ODF slices for `text:tab`, blockquote paragraph modifiers, heading auto/source ids, conditional/hidden/drop-down fields, dynamic script/macro/DDE fields, hidden paragraphs, database subtotals, data-pilot metadata, tracked table changes, or chart object handoff.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 2228 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - Result: `1 test files, 95 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`
- `php -l lanes/pandoc/src/OdfReader.php`
  - Result: no syntax errors detected
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - Result: no syntax errors detected
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - Result: no syntax errors detected

- JSON validation for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and `lanes/pandoc/lane-status.json`
  - Result: `json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output

Root harness: not run - isolated micro-slice.

## Status Delta

- Added one mapped ODF/OpenDocument support case.
- Mapped denominator: `2215 -> 2216`.
- ODF/OpenDocument core cases: `13 -> 14`.
- ODF/OpenDocument core assertions: `295 -> 330`.
- Focused ODF reader coverage: `1 test files, 2228 assertions, 0 failures`.
- Expected lane PHP PASS movement: `1795 -> 1796`.

## Dependency Closure

No new support component is needed. The slice reuses native `OdfReader` content/styles parsing, the existing ODT fixture path, `TableGeometry` review metadata, and `WordPressBlockWriter` table output.

## Follow-Up

A non-overlapping ODF follow-up should target named expressions, data-validity metadata, or table formula/value-type handoff. Do not duplicate row/column default-cell-style resolution.
