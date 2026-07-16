# ODF Data-Pilot Metadata Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260608T171700Z`

Base accepted HEAD: `a783f1c240f4f9420855f587c7aca332f110038d`

## Behavior

This slice maps one bounded ODF/OpenDocument content declaration cluster:
`table:data-pilot-tables` and nested `table:data-pilot-table`,
`table:data-pilot-field`, `table:data-pilot-level`,
`table:data-pilot-subtotal`, `table:data-pilot-member`, and source elements.

The parser preserves metadata for named data-pilot tables, target ranges,
source cell ranges, source SQL/table/query/service declarations, row/data field
orientation, aggregation functions, level flags, subtotals, member visibility,
and import-report counters. `table:source-service` passwords are redacted to a
`passwordPresent` marker and are not exposed.

This is metadata-only handoff. It does not execute pivot/data-pilot
calculations, spreadsheet formulas, database queries, Pandoc, Cabal, Haskell
runners, Word, LibreOffice, zip/unzip, or external converters.

## Evidence

Baseline before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2017 assertions, 0 failures
```

Focused verification after the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2070 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test
odf database field handoff self-test ok
```

Status movement:

- `lane-status.json` `phpPass`: `1695 -> 1696`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2115 -> 2116`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 348`

## Dependency Closure

No new support component is needed. The slice reuses `OdfReader` content XML
parsing, existing namespace helpers, Pandoc-like AST content declaration
metadata, import-report counters, `MarkdownWriter`, and
`WordPressBlockWriter`.

## Non-Overlap

This does not repeat accepted ODF coverage for text tabs, heading identifiers,
bookmarks, links, fields, dropdowns, hidden/conditional fields, database range
policy, named ranges/expressions, forms, charts, OLE objects, table styles, or
table captions. It targets the previously noted data-pilot metadata gap.
