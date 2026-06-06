# Malformed CMap Escaped-Key Filter Boundary Current Base

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T164934Z`

Accepted base: `469bb0ad08b27a0b7f2e9a45edbc0db9552c8619`

## Source Truth

Upstream markerPDF reaches searchable PDF text through pdftext/PDFium CMap decoding before OCR/model fallbacks. Under the current no-GPU lane scope, this patch ports the native parser boundary for CMap stream dictionaries whose top-level stream keys use PDF name escapes: `/Fil#74er`, `/Decode#50arms`, and `/Len#67th`.

The expected behavior is:

- Valid escaped CMap stream dictionary keys resolve exactly like `/Filter`, `/DecodeParms`, and `/Length`, so a Flate-decoded ToUnicode map can produce WordPress-visible text.
- A malformed extra filter-name operand after the escaped `/Length` value fails closed before CMap decoding, so fallback searchable text remains visible and corrupt CMap payload text is not imported.
- Review metadata counts escaped stream dictionary keys without exposing decoded CMap payload bytes or executing Python, OCR, models, or external PDF tools.

## Evidence

Red-first focused check after adding the new cases:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapEscapedKeyFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 40 assertions, 2 failures`. Both failures were the missing escaped-key review counters.

Focused check after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapEscapedKeyFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 91 assertions, 0 failures`.

Adjacent CMap filter boundary check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapEscapedKeyFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`

Result: `2 test files, 1644 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-escaped-key-filter-boundary-currentbase.php`

Result: emitted two safe paragraphs, `Escaped Key CMap Import` and `Escaped Key Safe Import`, with review metadata showing `executes_python_or_models=false`, `executes_external_pdf_tools=false`, valid decoded CMap count `1`, malformed decoded CMap count `0`, escaped stream dictionary key counts `3`, and malformed policy `reject_malformed_filter_operands`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted direct malformed CMap filter operands, duplicate filter declarations, escaped filter value names, CMap end-marker boundaries, xref/object-stream filter-owner recovery, missing/free indirect filter owner boundaries, or generic stream dictionary escaped-key parsing. This slice is specifically the ToUnicode CMap stream escaped-key boundary plus the post-Length extra filter-name fail-closed case.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP PDF parser, CMap stream decoder, stream filter operand review helpers, and top-level PDF value skipper.

Next useful markerPDF work: continue non-overlapping native searchable-PDF parser behavior around CMap owner/recovery, font widths, xref repair, stream filters, metadata, annotations, forms, page geometry, and image/filter metadata without GPU/model execution.
