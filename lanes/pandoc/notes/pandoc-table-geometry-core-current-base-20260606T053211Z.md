# Pandoc Table Geometry Caption Source Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T053211Z`
Base: `5461910d04f397e37087574b1ad2209244ea6334`

Implemented one bounded table-geometry support-library cluster: HTML table
`caption` element source metadata. The native HTML reader now preserves caption
element source attributes, table-child position, and CSS `caption-side`
metadata on the table AST. `TableGeometry` serializes that source packet into
long-caption review metadata and emits Markdown, AsciiDoc, and LaTeX writer
downgrade diagnostics when non-HTML writers would lose caption attributes.
`WordPressBlockWriter` carries safe caption source attributes to `figcaption`
while filtering unsafe event attributes.

Focused evidence:

- `php -l lanes/pandoc/src/MarkdownReader.php lanes/pandoc/src/TableGeometry.php lanes/pandoc/src/WordPressBlockWriter.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php lanes/pandoc/examples/wordpress-table-geometry-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 1244 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- `git diff --check -- lanes/pandoc` passed.

Dependency closure:

- Reused existing native PHP `MarkdownReader`, `TableGeometry`, and
  `WordPressBlockWriter` components.
- No new support component is needed.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external table
  writer, browser renderer, online sanitizer, online service, or live provider
  test was executed.

Non-overlap:

- This does not repeat accepted table span, alignment, row-head, section
  boundary, footer-section, block-cell, short-caption, or block-caption handoff
  work. It only preserves source metadata on the HTML `caption` element and
  records writer handoff requirements for that metadata.
