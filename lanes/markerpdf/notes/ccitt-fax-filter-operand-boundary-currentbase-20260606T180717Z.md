# CCITT Fax Filter Operand Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T180717Z`
Base: `11a2e57d1384f7898502502ab620e40838291fb1`

## Behavior

Native markerPDF import must keep image stream filters fail-closed when `/Filter`
does not resolve to a valid name/null/array stack, while still exposing enough
review metadata for WordPress media import decisions. Upstream markerPDF hands
image rasterization to PDFium/PIL/model-side code, but this no-GPU lane records
the boundary without decoding CCITT raster bytes.

This slice makes Image XObject boundary review preserve bad filter operands as
sentinel filters. Unresolved references now surface as `UnresolvedFilterOperand`
with `filter_operand_policy=reject_unresolved_filter_operands`; malformed array
slots surface as `MalformedFilterOperand` with
`filter_operand_policy=reject_malformed_filter_operands`. When the same
malformed stack still contains `CCITTFaxDecode`, the extractor keeps
`filters_resolved=false`, blocks native raster decode, preserves CCITT
preview/filter/decode metadata, and excludes image payload bytes from visible
WordPress paragraphs and review JSON.

## Evidence

- Focused run after source edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => `1 test files / 982 assertions / 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-operand-boundary-currentbase.php` emits `unresolved_filter_operand_rejected=true`, `malformed_filter_operand_rejected=true`, `unresolved_filter_operand_policy=reject_unresolved_filter_operands`, `malformed_filter_operand_policy=reject_malformed_filter_operands`, `malformed_review_filters=["FlateDecode","MalformedFilterOperand","CCITTFaxDecode"]`, `ccitt_preview_only_filters=["CCITTFaxDecode"]`, `payload_excluded_from_paragraphs=true`, `decoded_with_current_filters=false`, `native_raster_decode=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF dictionary
parser, indirect object resolver, image XObject boundary review, stream
DecodeParms alignment, and CCITT Fax review-only metadata. It does not execute
Python, models, pypdfium, PIL, external PDF tools, live OCR, or GPU/model
workers.

## Non-Overlap

This does not repeat accepted terminal CCITT exclusion, malformed/duplicate
CCITT DecodeParms handling, null-filter DecodeParms alignment, preview-only
filters before CCITT, post-CCITT filters, RunLength/Flate/LZW/ASCII85/Crypt
prefix ownership, direct EOFB/RTC/row ownership, escaped/comment filter parsing,
duplicate top-level CCITT `/Filter` declarations, DCT duplicate/malformed
filter coverage, CMap filter operand policy, encrypted filter preflight, or
attachment stream-filter stack work. It only covers unresolved and malformed
single `/Filter` operands on Image XObjects and the CCITT review metadata that
should remain observable while native raster decode stays blocked.
