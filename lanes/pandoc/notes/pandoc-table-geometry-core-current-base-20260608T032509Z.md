# Pandoc Table Geometry Current-Base Source Summary Handoff

Slice: `pandoc-table-geometry-core-current-base-20260608T032509Z`
Base accepted HEAD: `1a3460ad1b2631816d364821ee7b4164fb87413c`

## Behavior

- Added native table-geometry handoff for legacy HTML `<table summary="...">`
  source metadata.
- `TableGeometry::reviewPacket()` now exposes a named `sourceSummary` record
  with `text`, `source`, and `attribute`, and the review-packet `summary`
  rollup now includes `hasSourceSummary` and `sourceSummaryText`.
- Markdown, AsciiDoc, and LaTeX writer downgrade diagnostics now report table
  summary preservation/review requirements:
  - `markdown-table-summary-require-raw-html`
  - `asciidoc-table-summary-review-required`
  - `latex-table-summary-review-required`
- `WordPressBlockWriter` now treats `summary` as a safe table-level source
  attribute, preserving it in WordPress table output.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this
  slice before editing.
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  failed with `1 test files, 380 assertions, 1 failures` because expected
  `sourceSummary.text` was `NULL`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  passed with `1 test files, 393 assertions, 0 failures`.
- Focused table-geometry family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  passed with `2 test files, 1810 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  passed. The example smoke was also refreshed to expect the current two RST
  grid-table diagnostics for the existing span fixture.

## Status Delta

- Mapped denominator: `1957 -> 1958`.
- Lane PHP PASS cases: `1538 -> 1539`.
- Focused assertion growth for `TableGeometryReaderHandoffTest.php`: `+17`.

## Dependency Closure

No new support component is needed. The slice reuses native `MarkdownReader`
HTML table parsing, `TableGeometry` review-packet/writer-downgrade metadata,
`WordPressBlockWriter` table output, focused table-geometry tests, and the
lane-local WordPress table geometry handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, external writer,
browser renderer, online service, live provider test, or live-service provider
test was executed.

## Follow-Up

Choose a non-overlapping table-geometry gap next: DOCX/ODT/HTML source row or
column provenance, additional accessibility diagnostics outside source table
summary/header attributes, or writer downgrade metadata outside table summary,
colgroup, table-foot, and body-head row behavior.
