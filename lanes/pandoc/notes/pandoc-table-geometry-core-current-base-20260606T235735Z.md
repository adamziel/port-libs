# Pandoc Table Geometry Current-Base Slice

Micro-slice: `pandoc-table-geometry-core-current-base-20260606T235735Z`

Accepted base: `6d04ff33b7840d32f2f83f995941f5ec6af06983`

## Behavior

- Added native TableGeometry writer diagnostics for Markdown grid-table handoffs.
- Normalizes `markdown-grid-table`, `markdown+grid_tables`, and `pandoc-markdown-grid-table` aliases to one `markdown-grid-table` writer key.
- Emits a JSON-safe `markdown-grid-table-required` diagnostic with `requiredFeature: grid_tables`, span types, spanned cell records, and sectioned covered slots when a table contains rowspans or colspans.
- Keeps Markdown pipe-table flattening diagnostics separate from Markdown grid-table extension diagnostics.
- Extends the WordPress table geometry handoff smoke to require the Markdown grid-table review packet metadata.

## Source Truth

- Reused the lane-local upstream inventory and existing table-geometry source-truth rows for Pandoc Markdown writer table behavior.
- No upstream Pandoc checkout was available under `/home/claude/port-libs/.upstream-cache/pandoc` in this environment, and this slice did not run Pandoc, Cabal, Haskell runners, external writers, office tools, browser renderers, online services, live provider tests, or live-service provider tests.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` failed with `1 test files, 1203 assertions, 1 failures` because the new `markdown-grid-table` alias emitted no grid-table requirement diagnostic.
- Focused: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` passed with `1 test files, 1221 assertions, 0 failures`.
- Focused family: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 1562 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` printed `table geometry handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/TableGeometry.php`, `lanes/pandoc/tests/TableGeometryTest.php`, and `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.
- JSON parse check passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP TableGeometry coverage, writer diagnostic normalization, review packets, and the existing WordPress table geometry handoff example. Full upstream Pandoc runner parity still requires a hydrated pinned Pandoc checkout plus explicit authorization for Cabal/Haskell runner work.

## Non-Overlap

This slice avoids the already accepted table-foot, RST grid-table, pipe-table span flattening, block-cell, nested-table, source-attribute, and accessibility review-packet table geometry clusters. A useful next table-geometry follow-up would stay on non-overlapping Markdown grid or multiline table policy, reader source-position metadata, or writer-specific accessibility review metadata.
