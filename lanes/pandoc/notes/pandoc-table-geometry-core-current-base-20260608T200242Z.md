# Pandoc Table Geometry Directionality Handoff

Slice: `pandoc-table-geometry-core-current-base-20260608T200242Z`
Base: `e4416a27234df3582c58620f35f477531567f5a3`

Implemented one bounded table-geometry behavior cluster: HTML table `dir`
directionality is now preserved across table, row-group, row, and cell
metadata. `TableGeometry::reviewPacket()` reports explicit and inherited
direction records, coverage records expose the effective direction and source,
and Markdown/AsciiDoc/LaTeX writer handoff diagnostics flag the directionality
review requirement. `WordPressBlockWriter` preserves safe `dir` values
(`ltr`, `rtl`, `auto`) on table-related elements and drops invalid values.

Source truth and non-overlap:
- Reused existing native `MarkdownReader` HTML table parsing, table source
  attribute handoff, and table writer diagnostic conventions already present
  in this lane.
- No local Pandoc upstream checkout exists in `.upstream-cache/pandoc`, so this
  slice maps the format contract rather than upstream runner output.
- Avoided overlapping recent table-geometry slices for global row coordinates,
  source table summary attributes, footer writer diagnostics, block-cell
  content, RST grid-table requirements, and source header abbreviations.

Verification:
- Baseline focused family before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 2242 assertions, 0 failures`.
- Red-first directionality probe:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 575 assertions, 1 failures` because the review packet had
  no `directionality` metadata.
- Final focused reader handoff:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `1 test files, 602 assertions, 0 failures`.
- Final focused table-geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  -> `2 test files, 2273 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  -> `table geometry handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/TableGeometry.php`
  `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  all passed.

Status delta:
- Added `+1` focused PHP PASS case.
- Focused table-geometry family assertions increased from `2242` to `2273`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator updated
  `2196 -> 2197`.
- `lanes/pandoc/lane-status.json` `phpPass` updated `1778 -> 1779`.

Dependency closure:
- No new support component is needed. This reuses existing native
  `MarkdownReader`, `TableGeometry`, and `WordPressBlockWriter` support.
- Pandoc, Cabal/Haskell runners, external writers, browser renderers, online
  services, live provider tests, and live-service provider tests were not run.

Follow-up:
- A non-overlapping table-geometry follow-up could cover caption-side/layout
  policy, richer column-group semantics, or additional writer-specific table
  accessibility diagnostics.
