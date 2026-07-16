# markerPDF classic xref action-review rebuild boundary current-base

Session: `port-dev-markerpdf-xref-classic-rebuild-20260608T183553Z`
Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T183553Z`
Accepted base: `b920c43346788d744a7e02cd6f5b256da4f5f25d`

## Source Truth

Upstream markerPDF at the pinned manifest commit routes searchable-PDF imports
through parser-backed PDF object selection before OCR/model fallback. In the
native no-GPU PHP lane, classic xref repair is therefore the source-of-truth
boundary for annotation actions and WordPress link promotion.

The focused failure shape is a damaged producer file with:

- a valid current classic xref table selecting the current page annotation and
  indirect URI/additional-action objects;
- later direct-object decoys with the same object numbers after the table;
- a final top-level `startxref` operand that points outside the file.

Before this patch, `PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and
`PdfActionReviewExtractor` used local simple `startxref` readers. The damaged
operand made them fall back to raw direct-object order, so the later decoy URI
and JavaScript action replaced the current classic xref rows in annotation
review and Markdown link promotion.

## Implementation

- Added lane-local `PdfClassicXrefRebuilder` for native classic xref rebuild
  selection. It parses top-level `startxref` tokens, skips tokens in direct
  object bodies, comments, strings, hex strings, arrays, and dictionaries, and
  returns the latest valid classic xref table before the selected damaged
  boundary.
- `PdfActionReviewExtractor` now uses the rebuild helper before following its
  existing xref-table/xref-stream chain parser.
- `PdfAnnotationExtractor` and `PdfLinkAnnotationExtractor` now use the same
  rebuild helper and can select direct objects from either xref streams or
  classic xref tables before falling back to raw objects.
- Added body byte spans to the affected direct-object definition records so
  the rebuild scanner can reject object-owned `xref` and `startxref` text.

## Red-First Evidence

The red-first fixture selected:

- `https://stale.example.com/classic-rebuild-action-decoy`
- a stale `JavaScript` additional action named `staleClassicRebuildAction`

After the patch, the same fixture selects:

- `https://example.com/current-classic-rebuild-action`
- `mailto:current-classic-rebuild@example.test`

and the WordPress Markdown block remains:

`[Current action docs](https://example.com/current-classic-rebuild-action)`

## Verification

`php -l lanes/markerpdf/src/PdfClassicXrefRebuilder.php`

No syntax errors detected in `lanes/markerpdf/src/PdfClassicXrefRebuilder.php`.

`php -l lanes/markerpdf/src/PdfActionReviewExtractor.php`

No syntax errors detected in `lanes/markerpdf/src/PdfActionReviewExtractor.php`.

`php -l lanes/markerpdf/src/PdfAnnotationExtractor.php`

No syntax errors detected in `lanes/markerpdf/src/PdfAnnotationExtractor.php`.

`php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php`

No syntax errors detected in `lanes/markerpdf/src/PdfLinkAnnotationExtractor.php`.

`php -l lanes/markerpdf/tests/PdfXrefClassicRebuildActionReviewBoundaryCurrentBaseTest.php`

No syntax errors detected in
`lanes/markerpdf/tests/PdfXrefClassicRebuildActionReviewBoundaryCurrentBaseTest.php`.

`php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-action-review-currentbase.php`

No syntax errors detected in
`lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-action-review-currentbase.php`.

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildActionReviewBoundaryCurrentBaseTest.php`

Result: `1 test files, 21 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildActionReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewForwardPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildStreamPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildObjectOwnedStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildLiteralPercentStartxrefCurrentBaseTest.php`

Result: `9 test files, 955 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php`

Result: `2 test files, 388 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-action-review-currentbase.php`

Result: exits `0` with `annotation_uri_current=true`,
`additional_action_current=true`, `markdown_link_current=true`,
`excludes_stale_uri=true`, `excludes_stale_javascript=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted text/metadata/EmbeddedFiles/attachment classic
xref rebuild work for comments, arrays, names, literal strings, vertical tabs,
malformed rows, post-startxref trailers, forward `/Prev`, or free-object map
suppression. The bounded behavior is downstream annotation/action/link object
selection when the current classic xref table is valid but the final
`startxref` operand is damaged and later direct-object decoys exist.

## Dependency Closure

No new external support component is needed. The patch adds a bounded
lane-local native PHP helper and reuses existing direct-object scanning,
classic xref row parsing, xref-stream parsing, annotation extraction, action
review, link promotion, and WordPress smoke rendering. Live OCR,
Surya/Texify/Torch model execution, pypdfium/PDFium rendering, Python workers,
remote services, and external PDF tools remain intentionally out of scope for
this no-GPU markerPDF slice.

## Next

Continue native xref repair parity on non-overlapping downstream consumers,
preferably page geometry, forms, outlines, or security preflight paths that
still use direct-object fallback before current-table selection.
