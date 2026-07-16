# Pandoc Table Geometry Source Attribute Writer Diagnostics

## Summary

Pandoc native table AST nodes can carry key-value Attr metadata on the table,
table head/body/foot, rows, and cells. WordPress table handoff already renders
safe native attributes, but non-HTML writer review packets did not signal that
Markdown, AsciiDoc, and LaTeX need special handling for those source
attributes.

This slice adds bounded source-attribute writer diagnostics to
`TableGeometry::writerDowngradeDiagnostics()` and review packets:

- `markdown-table-source-attributes-require-raw-html`
- `asciidoc-table-source-attributes-require-raw-html`
- `latex-table-source-attributes-review-required`

The diagnostic records preserve attribute scope count, total attribute count,
scope names, and per-location table/section/row/cell coordinates. Attributes
mirrored from parsed HTML source attributes are filtered out, so existing
HTML-reader table packets keep their prior Markdown downgrade shape.

## Source Truth

This is bounded support-library behavior for Pandoc table Attr handoff into
native PHP review packets. It does not run Pandoc, Haskell runners, external
writers, office tools, browser renderers, online sanitizers, online services,
or live provider tests.

## Verification

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 845 assertions, 0 failures
```

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 846 assertions, 1 failures
missing markdown-table-source-attributes-require-raw-html
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 869 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

Focused family:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 1183 assertions, 0 failures
```

Syntax and whitespace:

```text
php -l lanes/pandoc/src/TableGeometry.php
No syntax errors detected in lanes/pandoc/src/TableGeometry.php
php -l lanes/pandoc/tests/TableGeometryTest.php
No syntax errors detected in lanes/pandoc/tests/TableGeometryTest.php
php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php
git diff --check -- lanes/pandoc
passed with no output
```

## Status Delta

- `lane-status.json` `phpPass`: `1194 -> 1195`
- `UPSTREAM_TEST_MANIFEST.json` `mappedTableGeometryCoreCases`: `6 -> 7`
- `UPSTREAM_TEST_MANIFEST.json` `tableGeometryCoreAssertions`: `74 -> 98`
- Added one mapped native table-geometry writer handoff case and 24 focused
  `TableGeometryTest.php` assertions.

## Dependency Closure

No new support component is needed. The slice reuses `AstNode`,
`TableGeometry`, and `WordPressBlockWriter`.

## Non-Overlap

This does not repeat accepted visual span, colspec, section-boundary rowspan,
rowHeadColumns, RST grid-table, footer writer, caption writer, block-cell,
nested-table, source-coordinate, source-attribute WordPress rendering, or
accessibility-header table geometry work. It only closes the native Pandoc
table key-value attribute writer-diagnostic gap for non-HTML writers.

## Follow-Up

Keep full upstream Pandoc writer rendering parity, CSS/table attribute cascade,
non-HTML writer rendering of arbitrary attributes, and Haskell runner
comparison as separate bounded slices.
