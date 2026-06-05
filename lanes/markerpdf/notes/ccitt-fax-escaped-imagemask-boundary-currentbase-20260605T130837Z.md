# CCITT Fax Escaped ImageMask Boundary - Current Base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T130837Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T130837Z`
Accepted base: `6925c7a86cf50689cbf5d524920848160f0f4231`

## Source truth

Upstream markerPDF treats searchable PDF text extraction as separate from image
rendering and uses image stream filters only as review/metadata handoff unless
native raster decode is available. Under the current no-GPU markerPDF scope,
CCITTFaxDecode/CCF image bytes stay out of visible WordPress paragraphs and
remain review-only image metadata.

This slice covers a producer boundary where an image XObject omits
`/Subtype /Image` but declares enough image-mask geometry to be an image stream:
`/Width`, `/Height`, escaped top-level `/Image#4Dask true`, `/Filter /CCF`,
and CCITT DecodeParms. Before the source change, the visible text path already
ignored the CCF payload, but `extractImageXObjectBoundaryReview()` missed the
resource because `isImageStreamDictionary()` only recognized raw
`/ImageMask true`.

## Native behavior

- `PdfTextExtractor::isImageStreamDictionary()` now uses a top-level,
  escaped-name-aware PDF boolean lookup for `ImageMask`.
- CCF/CCITTFax image-mask XObjects without `/Subtype /Image` are still
  classified as review-only image streams when they have width/height and
  top-level `ImageMask true`.
- The patch does not add raster decoding. `native_raster_decode` and
  `decoded_with_current_filters` remain false for CCITT fax payloads.
- Searchable text before and after the painted image is preserved, and fax
  payload bytes are excluded from visible WordPress text and review JSON.

## Evidence

Red-first probe before the source change:

`extractImageXObjectBoundaryReview()` on the escaped `/Image#4Dask true`
fixture returned `image_xobject_count = 0`; `extractTextLines()` still returned
`Before escaped ImageMask CCITT` and `After escaped ImageMask CCITT`.

Focused verification after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 414 assertions, 0 failures`.

Baseline for the same focused file before this slice was `1 test files, 394
assertions, 0 failures`, so the patch adds one focused PASS case and 20
focused assertions.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-ccitt-escaped-imagemask-currentbase.php`

Emits:

- `escaped_imagemask_without_subtype_classified=true`
- `escaped_imagemask_painted_resource_counted=true`
- `ccitt_payload_excluded_from_text=true`
- `ccitt_review_only=true`
- `ccitt_payload_excluded_from_review_json=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`
- `executes_pypdfium_or_pil=false`

## Non-overlap

This does not repeat the existing null-filter DecodeParms alignment slice, CCF
alias preservation, post-CCITT filter boundary coverage, native-prefix decode
metadata, inline CCITT boundaries, EOFB/RTC/EOL stream ownership, or Type3
CharProc work. The new coverage is specifically escaped top-level
`/Image#4Dask true` classification for CCF image-mask XObjects that omit
`/Subtype /Image`.

## Dependency closure

No new support component is needed. The implementation reuses the native PDF
dictionary scanner already present in `PdfTextExtractor`, and stays within the
no-GPU/no-model/no-external-PDF-tool markerPDF scope.
