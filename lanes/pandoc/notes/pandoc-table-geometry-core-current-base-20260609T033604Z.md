# Pandoc Table Geometry Current-Base Handoff

Slice: `pandoc-table-geometry-core-current-base-20260609T033604Z`
Base: `ee63dde665f0edb8e5a49e4c834317a2631d73ee`

## Behavior

This slice adds a bounded native table-geometry audit for duplicate tokens inside a single source HTML `headers` attribute. `TableGeometry` now reports duplicate-token cells in diagnostics, review-packet summaries, and writer downgrade metadata while preserving the existing de-duplicated resolved source-header references. `WordPressBlockWriter` now normalizes rendered table `headers` attributes by whitespace-tokenizing and de-duplicating source tokens, so malformed imported table accessibility does not leak duplicate references into WordPress output.

The behavior stays in the accepted table-geometry source-header handoff family and does not overlap the prior accepted duplicate header-id, ambiguous reference, resolved target geometry, row/column span, row-head, body-head, flat-grid, caption, or writer fallback slices.

## Evidence

Red-first baseline before this patch:

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`

Result: `1 test files, 1815 assertions, 0 failures`.

Final focused test after implementation:

`php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`

Result: `1 test files, 1862 assertions, 0 failures`.

Focused delta: `+1` PHP PASS case, `+47` assertions.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `AstNode` table metadata, `TableGeometry` source-header association/review-packet logic, `WordPressBlockWriter` table rendering, the focused `TableGeometryTest.php` coverage, and the existing WordPress table-geometry example. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, TeX/PDF engine, online service, live provider test, or live-service provider test was run.

## Next

A non-overlapping table-geometry follow-up can target source `axis`/`abbr` writer downgrade shape, rowgroup/colgroup source headers in another writer, or importer matrix handoff evidence.
