# Pandoc Table Geometry Core Current Base: Localization Handoff

Micro-slice: `pandoc-table-geometry-core-current-base-20260609T070256Z`
Base accepted HEAD: `53cc273b044292e061f08ae6f6fdabc37210dcb0`
Lane: `pandoc`

## Behavior

This slice adds bounded HTML table localization handoff support for table geometry:

- `TableGeometry` now records normalized `lang`, `xml:lang`, and `translate` metadata from table, section, row, and cell source attributes.
- Effective cell localization is resolved with the same table inheritance shape as HTML authors expect: cell, row, section, then table.
- Review packets now include `localization.table`, `localization.sections`, `localization.rows`, `localization.cells`, and localization summary fields.
- Markdown, AsciiDoc, and LaTeX writer review packets now report localization downgrade diagnostics because those writers cannot preserve the full HTML localization surface without raw HTML/reviewer intervention.
- `WordPressBlockWriter` now preserves safe `lang`, `xml:lang`, and `translate` table attributes and rejects malformed values.

The focused WordPress smoke in `wordpress-table-geometry-handoff.php --self-test` now checks that localized table/caption/section/row/cell attributes survive WordPress output.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 1286 assertions, 1 failures
```

Final focused checks:

```text
php -l lanes/pandoc/src/TableGeometry.php
No syntax errors detected in lanes/pandoc/src/TableGeometry.php

php -l lanes/pandoc/src/WordPressBlockWriter.php
No syntax errors detected in lanes/pandoc/src/WordPressBlockWriter.php

php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
No syntax errors detected in lanes/pandoc/tests/TableGeometryReaderHandoffTest.php

php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php

php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 1330 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 3218 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok

git diff --check -- lanes/pandoc
passed with no output
```

Focused delta: +1 PHP PASS case, +44 focused assertions, +1 mapped table geometry core case.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing Markdown/HTML reader source attribute capture, `TableGeometry` packet builder, and `WordPressBlockWriter` attribute sanitizer. No Pandoc, Cabal/Haskell runner, office tool, zip/unzip, external converter, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This slice avoids the accepted table geometry directionality, column background/border, cell dimension/nowrap/background/border, row/section presentation, frame/rules, spacing, caption metadata, and colgroup provenance clusters. It only owns localization metadata and safe WordPress table attribute handoff.

## Next

A useful non-overlapping follow-up would be caption-side writer diagnostics, richer column/row style conflict reporting, or additional malformed table recovery in the existing table geometry packet surface.
