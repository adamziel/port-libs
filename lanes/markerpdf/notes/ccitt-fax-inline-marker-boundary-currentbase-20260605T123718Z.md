# CCITT Fax Inline Marker Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T123718Z`  
Base: `f0c7b5dddf5f40d8781749792e99d534ff8a1444`

## Behavior

`PdfTextExtractor` now treats inline `CCITTFaxDecode` and `CCF` image payloads with direct DecodeParms EOFB, RTC, or row-EOL framing as complete inline-image tokenizer boundaries. The fax bytes remain review-only and are not raster-decoded or exposed as text; the marker is used only to stop a short compressed inline fax payload from consuming following text before a later inline image.

Source truth: upstream markerPDF hands CCITT image data through image rendering/review paths rather than text extraction. This native no-GPU port mirrors that boundary while using PDF CCITT DecodeParms framing to keep searchable text extraction stable.

## Evidence

Red-first before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`  
`1 test files, 387 assertions, 1 failures`

Focused after source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`  
`1 test files, 394 assertions, 0 failures`

Adjacent tokenizer family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`  
`2 test files, 650 assertions, 0 failures`

Adjacent text/image-filter family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`  
`3 test files, 1401 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-ccitt-fax-marker-boundary-currentbase.php`

The smoke emits `ccitt_eofb_inline_boundary_preserved_text_after_image=true`, `ccitt_eol_inline_boundary_preserved_text_after_image=true`, `inline_fax_payload_in_visible_text=false`, `native_raster_decode=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not repeat accepted CCITT XObject stream exclusion, malformed/unresolved DecodeParms fail-closed review metadata, escaped DecodeParms keys, null-filter alignment, Flate/Crypt prefix handling, CCF alias preservation, post-CCITT filter stacks, ImageMask polarity, nested image masks, XObject EOFB/RTC ownership, or generic inline-image fallback behavior. It only covers inline CCITT tokenizer closure when DecodeParms markers bound short compressed fax payloads before later inline images.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP dictionary parsing, filter-stack DecodeParms alignment, and CCITT marker ownership helpers. GPU/model OCR, native CCITT raster decoding, pypdfium, PIL, and external PDF tools remain intentionally out of scope for the current markerPDF lane.

## Next

Continue with non-overlapping native PDF parser behavior: font/CMap edge handling, xref repair, stream-filter metadata, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
