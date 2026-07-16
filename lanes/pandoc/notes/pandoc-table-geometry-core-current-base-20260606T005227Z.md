# Pandoc Table Geometry Footer-Section Writer Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T005227Z`
Base accepted HEAD: `c966e5ff0216e9268907832b43b9f7429fe085a0`

## Behavior

- `TableGeometry::writerDowngradeDiagnostics()` now reports
  `markdown-table-foot-flattened` when a Pandoc `table_foot` section will be
  flattened into ordinary body rows by the bounded Markdown pipe-table writer.
- The same row-section summary path now reports
  `asciidoc-table-foot-required` for AsciiDoc handoff packets so review queues
  can preserve that the source table had a footer section before choosing a
  writer strategy.
- The diagnostics reuse the existing serialized table section summary:
  caption, column count, head/body/foot row counts, body-group count, and
  section row roles.
- WordPress table output is unchanged and continues to render the source
  `<tfoot>` section for review.

## Source Truth

- Uses accepted pinned Pandoc static-inventory rows for table head/body/foot
  section behavior recorded in `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
  including `test/html-reader.html`, `test/html-reader.native`,
  `test/tables/nordics.html5`, and Pandoc AST `TableFoot` shapes already
  mapped by this lane.
- The local upstream Pandoc checkout was not hydrated in this isolated
  worktree, so this slice used accepted manifest/source rows and existing
  fixture-backed PHP tests rather than upstream Haskell execution.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external writer,
  browser renderer, online sanitizer, online service, or live provider test
  was executed.

## Verification

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 712 assertions, 1 failures`
  - Failure: expected `markdown-table-foot-flattened`, actual empty Markdown
    footer diagnostics.
- Focused table geometry after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 737 assertions, 0 failures`
- Focused table family:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `2 test files, 1051 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1125 -> 1126`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1577 -> 1578`
- `mappedTableGeometryCoreCases`: `6 -> 7`
- `tableGeometryCoreAssertions`: `74 -> 100`
- Added `mappedTableGeometryFooterSectionWriterCases: 1`
- Added `tableGeometryFooterSectionWriterAssertions: 26`

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`AstNode`, `TableGeometry`, `MarkdownWriter`, and `WordPressBlockWriter`
surfaces. Full upstream Pandoc runner parity remains gated on a hydrated pinned
Pandoc checkout plus Cabal/Haskell test executable closure.

## Non-Overlap And Follow-Up

This does not repeat accepted visual span layout, row-head output, body-local
head rows, section-scoped rowspans, declared-column overflow diagnostics,
source-coordinate metadata, source attributes, `rowspan=0`, colgroup
provenance, caption metadata, RST row-span requirements, AsciiDoc nested-table
or block-cell requirements, or the existing LaTeX longtable footer diagnostic.
This slice owns only Markdown/AsciiDoc table-foot section writer handoff
metadata.

Follow-up should keep default accessibility emission policy, broader
writer-specific row-group rendering policies, DOCX/ODF table-style inheritance,
full HTML5 table algorithm edge cases, and full upstream Pandoc Haskell runner
parity as separate bounded slices.
