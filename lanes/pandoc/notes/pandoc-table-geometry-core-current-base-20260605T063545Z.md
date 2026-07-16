# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260605T063545Z`

Base accepted HEAD: `beecd573326eb942861636d36f425d3bf3ca3af6`

## Behavior Added

- Added compact `sourceAttributes` summaries to `TableGeometry::reviewPacket()`
  for table nodes, table sections, table rows, and cell coverage/slot records.
- The summary keeps Pandoc-style `id`, `classes`, generic `attributes`, and
  source `htmlAttributes` visible for importer audits while keeping `AstNode`
  references out of serialized packets.
- Covered both direct Pandoc-like AST tables and HTML-reader attached geometry
  packets, so WordPress review queues can trace provenance from `<table>`,
  `<thead>/<tbody>`, `<tr>`, and `<td>/<th>` source attributes without relying
  on rendered HTML.
- Updated the WordPress table-geometry handoff smoke to assert source table,
  section, row, and cell attribute packet fields remain JSON-safe.

## Source Truth

- Uses the pinned Pandoc static inventory for table Attr-bearing structures:
  Pandoc table nodes carry Attr metadata on `Table`, `TableHead`,
  `TableBody`, `TableFoot`, `Row`, and `Cell`, and the accepted HTML-reader
  table inventory already maps attribute-carrying table nodes with section and
  row attributes.
- This slice ports bounded support-library behavior only. It does not invoke
  Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, office tooling,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, browser
  renderers, online sanitizers, or online services.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 345 assertions, 1 failures`
  - Failure: serialized table packets lacked table-level `sourceAttributes`.
- Focused table geometry:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 363 assertions, 0 failures`
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 458 assertions, 0 failures`
- Full focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8145 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Syntax and metadata checks:
  `php -l lanes/pandoc/src/TableGeometry.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors.
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`
  - Result: `manifest json ok`
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `708 -> 710` on the accepted lane counter basis; this slice adds
  two new focused PASS cases.
- Manifest mapped native checks: `1171 -> 1173`.
- Added `mappedTableGeometrySourceAttributeCases: 2`.
- Added `tableGeometrySourceAttributeAssertions: 39`.
- Focused table-family assertions: `419 -> 458`.

## Non-Overlap

This does not repeat accepted visual span layout, colspec preservation,
row-head WordPress output, body-local head rows, section-scoped rowspans,
declared-column overflow diagnostics, source-cell coordinate diagnostics,
overlap diagnostics, computed/source accessibility handoff, reader-attached
review packets, cell-level nested table rollups, or section-level nested table
summary records. The new behavior is source Attr serialization inside the
already accepted review-packet contract.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc-like table
AST, `MarkdownReader` HTML table attribute mapping, `TableGeometry` review
packet helper, and native WordPress table handoff smoke. Remaining table
follow-up work is writer-specific table downgrade diagnostics, richer malformed
source-coordinate normalization, default accessibility emission policy, and
full upstream Pandoc Haskell runner execution after the pinned checkout and
Cabal test executables are hydrated.
