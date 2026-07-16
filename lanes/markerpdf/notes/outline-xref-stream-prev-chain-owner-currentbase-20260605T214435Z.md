# Outline XRef Stream Prev Chain Owner Current Base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T214435Z`
Base accepted HEAD: `66d0408b47061a698c7ebd40ce9acc8de4ae0df1`

## Source Truth

Upstream markerPDF delegates PDF outline/text metadata to pdftext/PDFium-style parsing and does not require OCR/model execution for searchable-PDF xref owner selection. Under the current no-GPU markerPDF scope, the native PHP port owns this parser boundary.

PDF 1.5 xref streams use `/W` row widths plus `/Index` ranges to select the current owner row for each object. Incremental updates with `/Prev` must prefer the latest section rows, then fall back to previous sections only for objects not redefined by the current section.

## Behavior

`PdfOutlineExtractor` now builds current xref owner entries from the latest `startxref` whether it points to a classic table or an xref stream. Xref-stream entries decode Flate/no-filter payloads, honor `/W`, `/Index`, `/Size`, and `/Prev`, and keep latest-section rows authoritative before older `/Prev` rows.

The focused fixture places same-generation catalog, outline, destination, action, page, and content decoys after the current xref stream object but before the final `startxref`. WordPress TOC/navigation review and visible text now follow the xref-stream-selected current rows instead of scan-order post-xref decoys or stale previous-section rows.

## Evidence

Red-first focused run before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineXrefStreamPrevChainOwnerCurrentBaseTest.php`

Result: `1 test files / 1 assertions / 1 failures`; TOC selected `Post XRef Outline Decoy`.

After the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineXrefStreamPrevChainOwnerCurrentBaseTest.php`

Result: `1 test files / 16 assertions / 0 failures`.

Adjacent family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineXrefStreamPrevChainOwnerCurrentBaseTest.php`

Result: `60 test files / 3165 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-xref-stream-prev-chain-owner-currentbase.php`

Result: current TOC/navigation titles and current visible text emitted; previous-section outline, post-xref outline decoy, and post-xref JavaScript action decoy exclusions were true; no Python, model, OCR, or external PDF tools were invoked.

## Non-Overlap

This is not a repeat of earlier markerPDF xref-prev work for text metadata, classic xref owner rows, indirect `/Prev`, free-row owner repair, wrong current offsets, xref-stream root generation/index repair, annotations, forms, images, stream filters, or EOF-bounded outline traversal. The new covered boundary is specifically `PdfOutlineExtractor` object selection when the current incremental section is an xref stream with `/Prev` and post-xref same-generation outline/action/text decoys.

## Dependency Closure

No new support component is needed. The patch reuses the lane's native PHP token parser, xref-stream payload decode, and object-stream decode helpers. GPU/OCR/model parity remains intentionally out of scope for this native searchable-PDF behavior slice.
