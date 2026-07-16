# ODF/OpenDocument Data-Pilot Field Policy Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260609T053523Z`
Base accepted HEAD: `43b1a4a1010b27f9642a54fbdd65b896e3bf9eec`
Date: 2026-06-09 UTC

## Behavior

This slice adds bounded native PHP extraction for data-pilot field policy
metadata in ODF/OpenDocument content declarations.

- Preserves `table:data-pilot-display-info` as `displayInfo`.
- Preserves `table:data-pilot-sort-info` as `sortInfo`.
- Preserves `table:data-pilot-layout-info` as `layoutInfo`.
- Preserves `table:data-pilot-field-reference` as `fieldReference`.
- Reports `dataPilotDisplayInfoCount`, `dataPilotSortInfoCount`,
  `dataPilotLayoutInfoCount`, and `dataPilotFieldReferenceCount` in both
  `contentDeclarations` and `importReport.content`.

The data remains metadata-only. The reader does not evaluate pivot tables,
recalculate spreadsheet data, contact external data sources, or invoke office
tools.

## Evidence

Red-first focused check after adding the new ODF expectations and before the
reader change:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3075 assertions, 1 failures`; the new case failed on
missing `dataPilotDisplayInfoCount`.

Final focused verification:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 3110 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test`

Result: `odf database field handoff self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2382 -> 2383`
- `benchmarkDenominator.mapped`: `2776 -> 2777`
- ODF/OpenDocument mapped cases: `13 -> 14`
- ODF/OpenDocument focused assertions: `295 -> 349`
- Focused ODF test assertion delta: `+54`

## Dependency Closure

No new support component is needed. This reuses native PHP DOM parsing,
`OdfReader` content declarations, in-memory ODT `ZipPackage` fixtures,
`WordPressBlockWriter` smoke coverage, and the focused PHP test runner. Full
upstream Pandoc runner parity remains a separate upstream-runner dependency
task requiring hydrated pinned upstream sources and Haskell test executables.

## Non-Overlap

This does not repeat accepted ODF work for data-pilot table/source/member/
subtotal basics, named expressions, calculation settings, table print ranges,
table scenarios, data-style grammar, table-cell detective metadata, tracked
table changes, database ranges, dropdown fields, page/statistic fields, or
row-group table sections. The new surface is only field-level data-pilot
display, sort, layout, and reference policy metadata.

Good follow-up ODF slices: remaining data-pilot grouping metadata,
spreadsheet table style edge metadata, or reviewer-visible RDF/metadata
extraction.
