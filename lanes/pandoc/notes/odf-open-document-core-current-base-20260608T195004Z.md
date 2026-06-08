# ODF/OpenDocument Style Map Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260608T195004Z`
Base: `e33874b6a59046b0ea8a8d0d93a0e5bb2e4b1b0b`

## Behavior

Bounded native ODF/OpenDocument style parsing now preserves table-cell
`style:map` metadata:

- `style:condition`
- `style:apply-style-name`
- `style:base-cell-address`

The ODF reader keeps the maps on resolved style definitions, attaches them to
table-cell AST attributes, emits `data-odf-cell-style-map-*` attributes for
WordPress table handoff, and reports `styleMapCount` in the import report.
This is metadata preservation only; conditional style evaluation remains out of
scope.

## Evidence

- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  failed with `1 test files, 2105 assertions, 1 failures` because
  `odfCellStyleMaps` was absent.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with `1 test files, 2125 assertions, 0 failures`.
- Updated WordPress handoff smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  passed.

## Dependency Closure

No new support component is needed. This reuses the existing DOM-based ODF
reader, style resolver, table geometry handoff, and WordPress block writer.
No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

A later ODF slice can evaluate conditional style maps against concrete table
cell values if that becomes necessary. This patch intentionally only preserves
the source contract for importer review.
