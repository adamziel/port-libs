# Pandoc Table Geometry Duplicate Header ID Handoff

Slice: `pandoc-table-geometry-core-current-base-20260607T111450Z`
Base: `f0ab63b0aec4070b72a5ad36f42b8b417227d7b2`

## Behavior

- Added native table-geometry review metadata for duplicate source `th id` groups.
- Explicit source `headers` references that point to duplicate IDs now stay resolved but are also marked ambiguous with `targetCount` and all candidate `targets`.
- Review packets now include `table-header-id-duplicated` diagnostics plus duplicate/ambiguous summary fields.
- Markdown, AsciiDoc, and LaTeX source-header writer handoff diagnostics now include ambiguous-reference counts and IDs while WordPress output preserves the source table attributes.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` failed as expected with `1 test files, 1268 assertions, 1 failures` because duplicate source header IDs were not reported.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` passed with `1 test files, 1306 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.

## Non-Overlap

This avoids accepted table-geometry clusters for row-group ranges, colgroup provenance, body-local head-row writer diagnostics, source-header resolved/unresolved reference auditing, header abbreviations, nested tables, block cell content, empty tables, and RST rowspanned grid-table requirements. The new coverage is specifically duplicate source header IDs and ambiguous `headers` references.

## Dependency Closure

No new support component is needed. The slice reuses `TableGeometry`, `WordPressBlockWriter`, the native table handoff example, and focused PHP tests. Pandoc, Cabal/Haskell runners, external writers, browser renderers, online services, live provider tests, and live-service provider tests were not executed.
