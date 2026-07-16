# DOCX/OpenXML Table Cell Margins

Slice: `pandoc-docx-openxml-core-current-base-20260608T060644Z`

Accepted base: `cfec77028507d7bdc4213fc9124ee422079c0937`

## Behavior

The native DOCX reader now preserves bounded `w:tcPr/w:tcMar` table-cell margin metadata. Supported cell margin edges are `top`, `start`, `left`, `bottom`, `end`, and `right`; supported types are `dxa`, `pct`, and `auto`. Numeric positive `dxa` values are exposed as point padding, numeric positive `pct` values are exposed as percentage padding, and `auto` stays metadata-only. `nil` and unsupported margin types fail closed without emitting reviewer metadata.

The handoff flows through the existing table-cell source attribute path, so `TableGeometry` and `WordPressBlockWriter` retain the reviewer classes, data attributes, and safe inline padding styles alongside existing DOCX width, shading, border, vertical-alignment, span, caption, and row metadata.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`

Result: `1 test files, 2398 assertions, 1 failures`; the new table-cell margin assertion failed because only width and shading classes were present.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`

Result: `1 test files, 2429 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`

Result: `docx body handoff self-test ok`.

Status delta: +1 mapped DOCX/OpenXML case, +1 PHP PASS case, +52 focused assertions.

## Dependency Closure

No new support component is needed. This reuses the existing native `DocxReader`, `ZipPackage` fixture construction, table geometry source-attribute handoff, `WordPressBlockWriter`, focused DOCX reader tests, and the lane-local WordPress DOCX body handoff example.

No Pandoc, Word, LibreOffice, zip/unzip, Cabal/Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat the accepted DOCX run-language, embedded object/package, tracked-formatting-change, deleted OMML revision, structured document tag form-control, table caption/description, table preferred width/alignment/indent/style, cell width, cell shading, cell vertical-alignment, table grid-column, cell border, or row height/repeat-header/cant-split handoffs.

Follow-up: table-style/default cell-margin inheritance and conditional table-style cell properties remain out of this bounded slice.
