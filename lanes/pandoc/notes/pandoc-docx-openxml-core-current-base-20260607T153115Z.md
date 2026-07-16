# DOCX/OpenXML Table Cell Preferred Widths

Slice: `pandoc-docx-openxml-core-current-base-20260607T153115Z`
Base accepted HEAD: `ecdae3d672a8d414071d8e7c8995009a528f904e`

## Behavior

This slice adds bounded native PHP DOCX/OpenXML support for table-cell
preferred widths from `w:tcPr/w:tcW`.

- `w:type="dxa"` stores the raw twip value plus point-converted review
  metadata and emits a safe WordPress `td` width style.
- `w:type="pct"` stores the raw fiftieths-of-a-percent value plus percent
  metadata and emits a safe WordPress `td` width style.
- `w:type="auto"` is preserved as reviewer metadata without inventing CSS.
- `w:type="nil"` and unsupported width types stay inert.
- Combined cell metadata now preserves both width and shading style
  declarations instead of letting later table-cell properties overwrite the
  earlier style.

## Red-First Evidence

Before the reader change, the focused case failed because the first table cell
only had shading metadata and lacked the expected width metadata:

`php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`

Result before implementation: `1 test files, 2117 assertions, 1 failures`.

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`

Result: `1 test files, 2150 assertions, 0 failures`.

Focused delta from the pre-edit baseline is `+1` PASS case and `+35`
assertions.

The WordPress smoke was also updated:

`php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`

Result: `docx body handoff self-test ok`.

## Non-Overlap

This does not repeat accepted DOCX table-cell vertical alignment, table-cell
shading, table-row repeat-header/cant-split metadata, table spans/vertical
merges, table caption/description metadata, tracked changes, content controls,
media relationships, package properties, ODF table behavior, or shared table
geometry support. It is limited to `w:tcW` preferred-width handoff on existing
DOCX table cells.

## Dependency Closure

No new support component is needed. The slice reuses `DocxReader`,
`ZipPackage`, `AstNode`, `TableGeometry`, `MarkdownWriter`, and
`WordPressBlockWriter`.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external
office tooling, browser renderer, online sanitizer, online service, live
provider test, or live-service provider test was executed.

## Follow-Up

Keep table grid column width hints, style-derived table-cell property
inheritance, table/cell border metadata, and deeper Word layout parity as
separate bounded DOCX/OpenXML slices.

Root harness: not run - isolated micro-slice.
