# Pandoc Table Geometry Header Abbreviation Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T071409Z`

Base: `e736454ab8d70a32e8808e13291319ede97aa66f`

## Behavior

Pandoc-style table accessibility review packets now preserve source header-cell
`abbr` metadata in `TableGeometry::headerAssociations()` records and expose
packet summary fields:

- `headerAbbreviationCount`
- `hasHeaderAbbreviations`

The WordPress table handoff example also covers the user-visible path where
safe `<th abbr="...">` attributes are retained in rendered table output.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 903 assertions, 0 failures`
- Red-first after adding the focused case:
  - `1 test files, 905 assertions, 1 failures`
  - Missing `headerAbbreviationCount` in header association summaries.
- Final focused table test:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 917 assertions, 0 failures`
- Table geometry family:
  - `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 1258 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`TableGeometry`, `AstNode`, `MarkdownReader` HTML table attribute handoff, and
`WordPressBlockWriter` safe table-cell attribute rendering. No Pandoc, Cabal
solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip,
external writer, browser renderer, online sanitizer, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not rework prior table span layout, section-boundary rowspans,
declared-column overflow, table-foot writer diagnostics, block-cell content,
or XML/HTML5 DOM passive link relation behavior. It is a bounded table
accessibility metadata handoff for header abbreviations only.

## Follow-Up

Keep full upstream table reader/writer parity, richer DOCX/ODT table metadata,
and broader Haskell runner dependency closure as separate bounded slices.
