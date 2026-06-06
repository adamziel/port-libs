# Pandoc Table Geometry Colgroup Provenance Writer Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T134150Z`
Base: `972d7696f9725a30feefbe40aa423dceb19ed0c3`

## Source Truth

This slice stays within the existing native PHP table-geometry support surface. The lane already parses HTML/Pandoc column provenance into `columnSources`, groups it through `TableGeometry::columnGroups()`, and preserves complete colgroup width/alignment metadata for WordPress output. The remaining bounded gap was non-HTML writer review: Markdown pipe tables, AsciiDoc tables, and LaTeX tabular output need explicit downgrade diagnostics when source colgroup/col provenance would be lost or require raw writer-specific review.

No Pandoc, Haskell runner, external writer, browser renderer, Word, LibreOffice, zip/unzip, TeX/PDF engine, online service, or live-service provider test was run.

## Implementation

- Added `TableGeometry::columnGroupWriterDiagnostics()` and wired it into Markdown, AsciiDoc, and LaTeX writer downgrade summaries.
- The diagnostic records `columnGroupCount`, grouped column spans, grouped source kinds, source attribute group/count summaries, and the serialized `columnGroups` payload.
- WordPress remains the preservation path: the example self-test now proves the same colgroup source metadata is preserved for WordPress output while non-HTML writers receive review diagnostics.

## Verification

- `php -l lanes/pandoc/src/TableGeometry.php` -> no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php` -> no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php` -> no syntax errors.
- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` -> `1 test files, 1105 assertions, 0 failures`.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` -> `1 test files, 1143 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` -> `1 test files, 341 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` -> `table geometry handoff self-test ok`.

## Status Delta

- `lane-status.json` `phpPass`: `1336 -> 1337`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1750 -> 1751`.
- Table geometry core mapped cases: `8 -> 9`.
- Focused table geometry core assertions: `143 -> 181`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native `TableGeometry` column provenance model, current review packets, and current writer-downgrade framework. Full Pandoc writer parity for column-group rendering and hydrated upstream golden fixtures remain separate follow-up work.

## Non-Overlap

This does not repeat the accepted RST grid-table requirement, block-cell, footer-section, header-abbreviation, body-group, row-header, caption, or source-attribute table geometry slices. It is limited to colgroup/column provenance diagnostics for non-HTML writer handoff.
