# markerpdf outline root stream boundary current-base 2026-06-06T015302Z

## Scope

Bounded native no-GPU markerPDF slice for catalog `/Outlines` references that
resolve to stream objects. Outline roots and outline items are navigation
metadata dictionaries; a stream object's leading dictionary must not own the
document bookmark tree or leak stream payload/action data into metadata,
navigation review, lightweight `pdf_toc`, or visible WordPress text.

## Source truth

Upstream markerPDF obtains PDF TOC/bookmark rows from the PDF backend as
document navigation metadata before OCR/model stages. This port keeps the same
contract in native PHP: outline metadata is parsed from catalog/outlines
dictionaries and stays separate from page stream text. Under the current lane
scope, no OCR, model execution, Python PDF runners, or external PDF tools are
used.

## Patch

- Added `PdfOutlineMetadataRootStreamBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-outline-root-stream-boundary-currentbase.php`.
- `PdfOutlineExtractor` now rejects referenced outline roots and outline item
  objects whose parsed object has extra top-level stream tokens.
- `PdfMetadataExtractor` now rejects catalog `/Outlines` roots and outline item
  rows when the referenced object body is a stream object.
- `PdfTextExtractor` now keeps lightweight `pdf_toc` traversal on raw object
  bodies while rejecting stream objects as outline root/item owners.

## Red-first evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootStreamBoundaryCurrentBaseTest.php`

Result: `1 test files, 4 assertions, 2 failures`.

The failures showed a stream-carried `/Type /Outlines` dictionary promoted
`Rejected Stream Root Outline` into `document_outline` and native TOC rows.

## Final evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootStreamBoundaryCurrentBaseTest.php`

Result: `1 test files, 20 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRootTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`

Result: `36 test files, 1646 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-pdf-outline-root-stream-boundary-currentbase.php`

Result: smoke passed with `page_mode_preserved=true`,
`document_outline_absent=true`, `toc_empty=true`,
`navigation_outline_empty=true`, `lightweight_toc_empty=true`,
`stream_root_payload_excluded=true`, `stream_root_action_excluded=true`,
`visible_text_imported=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-overlap

This slice does not repeat the earlier indirect `/Title` scalar boundary,
direct outline root stale-parent boundary, outline item `/Metadata` stream
boundary, root type/count boundaries, zero-count rows, `/Prev` and `/Last`
sibling bounds, missing-parent bounds, titleless bridge bounds, current
trailer/xref selection, action-chain review, named-destination/PageLabels
metadata, annotations, forms, fonts, CMaps, images, security preflight, or
table/equation supplied-boundary handoffs.

## Dependency closure

No new support component is needed. The implementation reuses native PHP PDF
object parsing and stream-object detection already present under
`lanes/markerpdf/src`. No Python, model, GPU, OCR, PDFium, or external PDF
tools are required for this boundary.
