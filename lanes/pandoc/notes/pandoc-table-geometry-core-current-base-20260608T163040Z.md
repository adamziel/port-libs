# Pandoc Table Geometry Writer Section Ranges

Slice: `pandoc-table-geometry-core-current-base-20260608T163040Z`
Base accepted HEAD: `f7bb0ce56c95f19eaed5b64a386c252d4eb5269a`

## Behavior

This slice keeps existing table-section writer diagnostics compatible while adding flattened section range context for consumers that need to map downgraded writer output back to Pandoc table sections.

- `markdown-table-foot-flattened`, `asciidoc-table-foot-required`, and `latex-longtable-footer-required` diagnostics now include `sectionRanges` and `footSectionRanges`.
- Multiple body group diagnostics now include `sectionRanges` and `bodySectionRanges`.
- Body-local head-row diagnostics now include `sectionRanges`, `bodySectionRanges`, and `bodyHeadRowRanges`.
- Existing `sections` arrays are preserved unchanged.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` passed with `1 test files, 1658 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted table row-span/col-span parsing, row-group review packets, global row-coordinate review packets, duplicate header identifiers, colgroup provenance, footer-section writer diagnostics, body-local head-row diagnostics, multiple-body writer diagnostics, block-cell handoffs, nested-table diagnostics, empty-table omission, or RST grid-table requirement behavior. The change is limited to range metadata on existing writer downgrade diagnostics.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `TableGeometry`, existing Markdown/AsciiDoc/LaTeX writer diagnostics, `WordPressBlockWriter`, and the existing WordPress table-geometry smoke. Pandoc, Cabal/Haskell runners, external writers, browser renderers, online services, live provider tests, and live-service provider tests remain out of scope for this slice.
