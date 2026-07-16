# Pandoc Table Geometry Core: Legacy Table Spacing

Slice: `pandoc-table-geometry-core-current-base-20260608T203135Z`
Base accepted HEAD: `bb37a42dff2002404bb134df44da31542c787c36`

## Behavior

This slice adds bounded native handoff for legacy HTML table `cellpadding` and
`cellspacing` attributes:

- `TableGeometry::reviewPacket()` now emits `tableSpacing` metadata when a
  table carries numeric `cellpadding` or `cellspacing` source attributes.
- Review packet summaries expose `hasTableSpacing`, `tableCellPadding`,
  `tableCellSpacing`, and `tableSpacingAttributeCount`.
- Markdown, AsciiDoc, and LaTeX writer diagnostics flag that legacy spacing
  needs raw HTML or reviewer handling because the native table formats do not
  carry those attributes directly.
- `WordPressBlockWriter` renders sanitized numeric legacy spacing attributes on
  table blocks and drops unsafe non-numeric values such as CSS lengths or
  negative values.

## Source Truth And Non-Overlap

The lane has no hydrated local Pandoc upstream checkout for runner parity, so
this ports the format contract through the existing native Markdown/HTML table
reader, table geometry review packet, and WordPress table writer path. The
slice avoids existing accepted table geometry clusters for row groups,
rowspans, colgroups, decimal alignment, captions, border/frame/rules metadata,
directionality, nested table rollups, block-cell content, and accessibility
header abbreviations.

## Verification

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 2281 assertions, 0 failures`.
- Focused reader handoff: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `1 test files, 626 assertions, 0 failures`.
- Focused family: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 2305 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.

## Dependency Closure

No new support component is needed. This slice reuses `MarkdownReader` HTML
table attributes, `TableGeometry` review packets and writer diagnostics, and
`WordPressBlockWriter` table rendering. Pandoc, Cabal/Haskell runners, Word,
LibreOffice, external writers, browser renderers, online services, live
provider tests, and live-service provider tests were not run.

Root harness status: not run - isolated micro-slice.
