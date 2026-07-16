# Pandoc Table Geometry AST Attribute Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T025842Z`

Base accepted HEAD: `4809dec50dd6ae6c74834d67a44d49faade4d0dd`

## Behavior

Pandoc table AST nodes can carry key-value Attr metadata on the table, table
head/body/foot, rows, and cells. The review packet already preserved those
source attributes, but the WordPress table writer only emitted attributes that
arrived through parsed HTML `htmlAttributes`. This slice maps safe native AST
key-value attributes into the existing WordPress table attribute renderer for
table, section, row, and cell output.

Parsed `htmlAttributes` remain authoritative when both maps define the same
attribute, and the existing table allowlist still blocks unsafe event
attributes such as `onclick` and `onmouseover`.

## Source Truth

This is bounded support-library behavior for Pandoc table Attr handoff into
WordPress HTML. It is intentionally native PHP only and does not run Pandoc,
Haskell test binaries, office tools, external writers, browser renderers, or
online services.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 834 assertions, 1 failures
missing data-pandoc-source="native-ast" in WordPress output
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 845 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok
```

Syntax and whitespace:

```text
php -l lanes/pandoc/src/WordPressBlockWriter.php
No syntax errors detected in lanes/pandoc/src/WordPressBlockWriter.php
php -l lanes/pandoc/tests/TableGeometryTest.php
No syntax errors detected in lanes/pandoc/tests/TableGeometryTest.php
php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php
git diff --check -- lanes/pandoc
passed with no output
```

## Status Delta

- `lane-status.json` `phpPass`: `1167 -> 1168`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1617 -> 1618`
- Added one mapped native table-geometry WordPress handoff case.

## Dependency Closure

No new support component is needed. The slice reuses `AstNode`,
`TableGeometry`, and `WordPressBlockWriter`.

## Non-Overlap

This does not repeat accepted visual span, colspec, row-head column,
section-scoped rowspan, footer writer-diagnostic, block-cell content, nested
table, accessibility-header, inherited HTML attribute, or vertical alignment
table geometry work. It only closes the native AST key-value attribute handoff
gap for safe WordPress table attributes.

## Follow-Up

Keep full upstream table fixture parity, richer colgroup/CSS cascade
interactions, writer-specific non-HTML attribute downgrade packets, and full
Pandoc runner parity as separate bounded slices.
