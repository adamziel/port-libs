# Pandoc Table Geometry Source Header Reference Audit

Slice: `pandoc-table-geometry-core-current-base-20260606T151943Z`
Base: `7f8e868beeae24e1de79173228c726eeee807d87`

## Source Truth

Pandoc preserves table cell attributes in the native AST, and imported HTML
tables may carry explicit `headers` tokens that point to `th` ids. The existing
native table geometry path already preserved those tokens for WordPress output,
but reviewer packets only exposed them as opaque overrides. This slice keeps
the rendered output unchanged while classifying explicit source header tokens
as resolved or unresolved against the imported header-cell id set.

No Pandoc, Cabal solver/build/test command, Haskell runner, external writer,
browser renderer, online sanitizer, online service, live provider test, or
live-service provider test was run.

## Implementation

- `TableGeometry::headerAssociations()` now indexes imported header-cell ids
  and annotates explicit source `headers` tokens with `sourceHeaderReferences`.
- Resolved records carry the target header key, section, row, column, scope,
  text, and covered columns. Unresolved legacy ids remain visible as
  `resolved: false`.
- Header association summaries and review-packet summaries now include source
  header reference counts and unresolved token lists without changing the
  existing `sourceHeaderOverrideCount` data-cell override meaning.
- The WordPress table geometry handoff self-test now checks resolved and
  unresolved source-header review metadata while preserving existing rendered
  `headers` attributes.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  -> `1 test files, 1141 assertions, 1 failures` before the resolver.
- Focused: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  -> `1 test files, 1167 assertions, 0 failures`.
- Focused family: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 1508 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  -> `table geometry handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/TableGeometry.php`,
  `lanes/pandoc/tests/TableGeometryTest.php`, and
  `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.
- `git diff --check -- lanes/pandoc` -> passed.

## Status Delta

- `lane-status.json` `phpPass`: `1353 -> 1354`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1767 -> 1768`.
- `mappedTableGeometryCoreCases`: `8 -> 9`.
- Added `mappedTableGeometrySourceHeaderReferenceCases: 1`.
- Added `tableGeometrySourceHeaderReferenceAssertions: 15`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`TableGeometry`, `MarkdownReader`, and `WordPressBlockWriter` support paths.
Full upstream runner parity remains blocked on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and separately authorized
Haskell/Cabal runner work.

## Non-Overlap

This does not repeat accepted table geometry work for visual spans,
section-scoped rowspans, row-header rendering, body-local head rows, source
attributes, header abbreviations, colgroup provenance, footer sections, nested
table diagnostics, block-cell writer handoffs, or writer downgrade requirements.
It owns only explicit source `headers` token resolution for reviewer audit
packets and the directly coupled WordPress smoke.

## Follow-Up

Keep explicit writer diagnostics for unresolved source headers, header-id
collision policy, richer DOCX/ODT table accessibility handoff, and full
upstream golden fixture parity as separate bounded slices.
