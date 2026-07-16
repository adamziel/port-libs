# Pandoc Table Geometry Core Current Base - Row Header Writer Diagnostics

Date: 2026-06-06 UTC
Base accepted HEAD: `acaa655f41a326695b1b8edaa14a30da83e3ddae`
Micro-slice: `pandoc-table-geometry-core-current-base-20260606T105717Z`

## Behavior

Implemented bounded native PHP table-geometry writer diagnostics for Pandoc row-header maps. Tables with body `rowHeadColumns` now report row-header semantic loss for non-HTML/plain table writers:

- Markdown pipe-table handoff: `markdown-row-headers-flattened`, required feature `pipe-table-row-header-semantics`.
- AsciiDoc handoff: `asciidoc-row-headers-review-required`, required feature `row-header-review`.
- LaTeX handoff: `latex-row-headers-review-required`, required feature `row-header-review-comments`.

The diagnostic record reuses `TableGeometry::rowHeaderMap()` data, including row header IDs, texts, scopes, row counts, unlabeled-row counts, and rowspan row-header counts. `reviewPacket()` passes its id prefix into writer diagnostics so row-header diagnostic IDs match the packet row-header map. WordPress writer handoff remains empty for this downgrade class because WordPress table output already preserves row headers as `<th>` cells with scope/header metadata.

## Non-Overlap

This slice does not repeat the accepted footer-section, block-cell, RST grid-table, header-abbreviation, source-attribute, or declared-column overflow table geometry slices. It implements the next bounded follow-up noted by prior table geometry work: writer-specific diagnostics for lossy row-header maps in plain table writers.

## Verification

Baseline before implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
# 1 test files, 993 assertions, 0 failures
```

After implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
# 1 test files, 1033 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
# 1 test files, 341 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
# table geometry handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TableGeometry`, existing row-header maps, writer alias normalization, review-packet summaries, and the WordPress table handoff path. No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, external Markdown/AsciiDoc/LaTeX writer, browser renderer, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Next

Keep actual Markdown/AsciiDoc/LaTeX row-header rendering mitigation, additional writer syntax policy, and full upstream Pandoc writer golden parity as separate bounded slices unless concrete fixtures require them.
