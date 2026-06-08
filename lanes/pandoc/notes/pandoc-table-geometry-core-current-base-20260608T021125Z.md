# Pandoc Table Geometry Alignment Constructor Handoff

Slice: `pandoc-table-geometry-core-current-base-20260608T021125Z`
Base: `7ed7b0181dae439571f64983f19fbb9b6bfce3fe`

## Behavior

`TableGeometry` now normalizes Pandoc-style table alignment constructor names
before Markdown and WordPress handoff:

- `AlignLeft`, `AlignRight`, and `AlignCenter` map to `left`, `right`, and
  `center`.
- `AlignDefault` remains `default`.
- Closely related handoff spellings such as `align-right` and
  `text-align: center` are normalized through the same helper.

This keeps native AST-like table colspecs from losing alignment when they pass
through `TableGeometry::alignments()`, `TableGeometry::columnSpecs()`,
`TableGeometry::cellAlignment()`, `MarkdownWriter`, and
`WordPressBlockWriter`.

## Verification

Red-first focused check after adding the case:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1402 assertions, 1 failures
```

The expected failure was that `AlignLeft`, `AlignRight`, and `AlignCenter`
were treated as `default`.

Final focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1409 assertions, 0 failures
```

Focused family check:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 1772 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Added one mapped native table geometry support case.
- Added one PHP PASS case and 8 focused assertions in `TableGeometryTest.php`.
- Updated `UPSTREAM_TEST_MANIFEST.json` mapped denominator from 1956 to 1957.
- Updated `mappedTableGeometryCoreCases` from 8 to 9.
- Added `mappedTableGeometryAlignmentAliasCases: 1`.
- Updated lane `phpPass` from 1537 to 1538.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`TableGeometry`, `MarkdownWriter`, `WordPressBlockWriter`, focused table
geometry tests, and the existing WordPress table geometry handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, external writer,
browser renderer, online service, live provider test, or live-service provider
test was executed.

## Non-Overlap

This avoids accepted table geometry clusters for span layout, section-scoped
rowspans, row headers, source `headers` auditing, duplicate header IDs, global
row coordinates, row occupancy summaries, colgroup provenance, vertical
alignment, source attributes, footer/body-head writer diagnostics, nested
tables, block-cell content, empty tables, and RST grid-table requirements. The
new behavior is limited to alignment alias normalization for existing table
AST/Markdown/WordPress handoff paths.

## Follow-Up

Future table geometry work should stay on non-overlapping writer diagnostics
or reader metadata normalization, such as writer-specific alignment loss
diagnostics or remaining DOCX/ODT table metadata handoff.
