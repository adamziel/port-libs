# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T055321Z`
- Accepted base: `5e96d584fc166b2103fc2ab7cd01f6880630330b`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for annotation
ranges:

- Pairs `office:annotation` with `office:annotation-end` by `office:name`
  while walking inline sibling nodes.
- Wraps the annotated inline source range in an `odf-annotation-range` span.
- Preserves annotation author/date metadata as AST metadata and safe
  `data-odf-annotation-*` attributes for Markdown/WordPress review output.
- Keeps the annotation body as a native note child so WordPress imports expose
  the comment in footnotes instead of dropping reviewer context.
- Reports `importReport.content.annotationRangeCount`.
- Updates the WordPress ODF handoff smoke so range comments survive rendered
  WordPress blocks.

Source truth: OASIS OpenDocument 1.3 schema lists `office:annotation` and
`office:annotation-end` as paragraph children, and Pandoc's reader options
document that comment inclusion is represented with comment range spans.
This PHP slice ports that bounded review-comment handoff without invoking
Pandoc or office tooling.

Sources checked:

- https://docs.oasis-open.org/office/OpenDocument/v1.3/cs02/part3-schema/OpenDocument-v1.3-cs02-part3-schema.html
- https://pandoc.org/demo/example33/3.2-reader-options.html

This is bounded to OpenDocument content XML mapping. It does not invoke
Pandoc, LibreOffice, Word, zip/unzip, browser renderers, external template
engines, TeX/PDF engines, Haskell runners, or online services.

## Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 437 assertions, 0 failures`
- Red-first after adding the annotation-range expectation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 439 assertions, 1 failures`
  - Expected failure: the reader emitted a point `note` before the annotated
    text instead of an `odf-annotation-range` span.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 457 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `669 -> 670`.
- `benchmarkDenominator.mapped`: `1149 -> 1150`.
- Focused `OdfReaderTest.php`: `18 -> 19` cases, `437 -> 457` assertions.
- ODF manifest subcounters now reflect the current focused ODF file:
  `19` mapped cases / `457` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`ZipPackage`, PHP DOM/XML parsing, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter` components. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but ODT-local annotation-range parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice avoids the accepted ODT mimetype/content/manifest/media/table/list
restart/list continuation/annotation point-note/text-box/image presence,
footnote/endnote, bookmark-reference, reference-mark/reference-ref, sequence,
field, bibliography-mark, tracked-change, encrypted-manifest, MathML object,
linked/protected section, page-layout/master-page, and image-dimension
clusters. It adds only bounded OpenDocument annotation range handoff.

Remaining ODT follow-up stays separate: forms, charts, richer style cascades,
embedded-object preview policy beyond MathML, table continuation semantics,
export-side ODT writing, and full Pandoc ODT reader parity.
