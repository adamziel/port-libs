# DCTDecode Malformed Stream Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T180516Z`
Accepted base: `11a2e57d1384f7898502502ab620e40838291fb1`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image rendering: text blocks are extracted from PDF text/content operators, while image payloads are media inputs for rendering or review. In the no-GPU PHP lane, DCT/JPEG image bytes must remain review-only unless a native raster backend proves a valid image decode path. Malformed Image XObject bytes should not be promoted to WordPress paragraphs or renderer pixels.

## Red Probe

On the current base, no-SOI and no-EOI DCTDecode Image XObject streams already stayed out of visible text, but the image review row had no `dctdecode_stream_boundary` metadata:

`no_soi_no_length => [["Before no_soi_no_length","After no_soi_no_length"],"Before no_soi_no_length\nAfter no_soi_no_length",true,68,false,null]`

`soi_no_eoi_no_length => [["Before soi_no_eoi_no_length","After soi_no_eoi_no_length"],"Before soi_no_eoi_no_length\nAfter soi_no_eoi_no_length",true,81,false,null]`

That left WordPress import review unable to distinguish a deliberately skipped malformed DCT image from an uninspected image-only stream.

## Implementation

- `PdfTextExtractor` now records fail-closed `dctdecode_jpeg_marker_boundary_unverified` metadata when a DCTDecode stream cannot prove JPEG SOI/EOI marker framing.
- `PdfImageRenderer` mirrors the same metadata for review-only image stream previews and still reports no pixels and no native raster decode.
- Missing SOI and missing EOI boundaries include explicit `invalid_reason`, `valid_jpeg_marker_boundary=false`, `review_only=true`, `native_raster_decode=false`, and `payload_in_visible_text=false`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeMalformedStreamBoundaryCurrentBaseTest.php` => `1 test files, 76 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecode*.php` => `14 test files, 1171 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-malformed-stream-boundary-currentbase.php` emitted `missing_jpeg_soi`, `missing_jpeg_eoi`, `valid_jpeg_marker_boundary=false`, `dctdecode_payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `renderer_native_raster_decode=false`.

Final lint and diff-whitespace checks are recorded in the handoff response.

## Non-Overlap

This slice does not repeat valid DCT SOI/EOI boundary recovery, APP/SOS parsing, post-EOI surplus clipping, comments after EOI, stale or missing declared Length recovery, prefix filter stacks, null/trailing filters, DecodeParms, malformed filter operands, inline DCT image scanning, soft mask/mask/alternate image review, CCITT/JPX/JBIG2 boundaries, OCR/model execution, or native raster decode.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF parser, stream filter resolver, Image XObject review metadata, and renderer preview rows. Full JPEG raster decoding remains a future native/PDFium/PIL-style backend decision and is outside this no-GPU, no-model markerPDF slice.

## Next Task

Continue non-overlapping native PDF parser/filter work around image/filter metadata, xref repair, fonts/CMaps, metadata, annotations, forms, page geometry, and supplied-boundary table/equation handoffs.
