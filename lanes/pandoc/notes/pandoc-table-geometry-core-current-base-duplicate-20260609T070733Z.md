# Pandoc Table Geometry Duplicate Source Ids

Slice: `pandoc-table-geometry-core-current-base-duplicate-20260609T070733Z`
Base: `030e94cf137586963da96dca64555cebe2ff01ee`
Date: 2026-06-09 UTC

## Behavior

- Added native table-geometry source-id inventory records for HTML table, section, row, and cell nodes.
- Added grouped review-packet diagnostics for duplicate non-header-only source ids, including section/row/cell coordinates and duplicate scope rollups.
- Added markdown/asciidoc/latex writer downgrade diagnostics for duplicate source ids that need raw HTML or reviewer comments outside the WordPress HTML handoff.
- Preserved WordPress table output for duplicate section, row, and data-cell ids so reviewers can audit malformed source markup without losing content.

This intentionally does not replace the existing duplicate header-id/accessibility diagnostic. Header-only duplicate ids remain reported by the established `table-header-id-duplicated` path.

## Evidence

Red-first focused check before production support:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
FAIL reports duplicate html source ids beyond header-only collisions for geometry and wordpress handoff
Expected: true
Actual: NULL
1 test files, 1283 assertions, 1 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 1314 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 3202 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

Status delta:

- `phpPass`: `2470 -> 2471`
- `benchmarkDenominator.mapped`: `2851 -> 2852`
- Focused assertion delta: `TableGeometryReaderHandoffTest.php` `1280 -> 1314` (`+34`)

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`MarkdownReader` HTML table parsing, `TableGeometry` coverage/source-attribute
helpers, `WordPressBlockWriter` table output, and the focused PHP TestRunner.
Full upstream Pandoc runner parity remains a separate upstream-runner dependency
task requiring a hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted table geometry work for duplicate header ids,
duplicate `headers` tokens, invalid source scopes, row/section/cell
presentation metadata, source-to-visual shifts, row-head columns, or declared
column overflow. It covers only duplicate non-header-only HTML source ids across
table sections, rows, and data cells.

Root harness not run: isolated micro-slice.
