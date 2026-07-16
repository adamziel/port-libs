# markerPDF DCTDecode Duplicate Filter Boundary Current Base

Session: `port-dev-markerpdf-dctdecode-filter-20260605T230423Z`

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T230423Z`

Base accepted HEAD: `5fae3aaf3862010b2519a5c1a9820543a52b22e3`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` treats PDF image rendering as a PDFium/PIL boundary and keeps image payload bytes out of searchable text extraction. The no-GPU PHP port cannot rasterize DCT/JPEG streams, so the native boundary is strict: unsupported or malformed Image XObject filters must remain review-only, and visible text must not be recovered from image stream bytes.

PDF stream dictionaries declare `/Filter` once. Duplicate top-level `/Filter` declarations are malformed in the native parser. This slice rejects duplicate image filter declarations for native decoding while preserving enough flattened review metadata to identify DCTDecode as preview-only for WordPress media/import diagnostics.

## Red First

Before the source fix, a duplicate Image XObject dictionary shaped as `/Filter /FlateDecode /Filter /DCTDecode` already stayed out of visible text, but `extractImageXObjectBoundaryReview()` lost the DCT boundary metadata because strict stream-filter resolution returned `null`. The direct ICCBased renderer path also treated the stream as not review-only and threw `InvalidArgumentException: ICCBased image stream filters must be natively decoded before RGB preview`.

The focused fixture captures both failures as a searchable page plus direct renderer image stream.

## Implementation

- `PdfTextExtractor::imageXObjectBoundaryEntry()` now keeps strict duplicate-filter rejection for actual decoding, but flattens duplicate `/Filter` operands into review-only metadata when building Image XObject filter details.
- The review row reports `duplicate_filter_declaration_count` and `filter_operand_policy=reject_duplicate_filter_declarations`, keeps `filters_resolved=false`, and preserves DCTDecode filter-boundary metadata.
- `PdfImageRenderer` now treats duplicate image `/Filter` declarations as a malformed filter operand followed by the flattened declared filters. This makes ICCBased image stream preview fail closed as review-only instead of attempting native samples.

## Verification

Focused behavior:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDuplicateFilterBoundaryCurrentBaseTest.php
```

Passed: `1 test files, 46 assertions, 0 failures`.

Adjacent DCT family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDuplicateFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSosMarkerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeMaskBoundaryCurrentBaseTest.php
```

Passed: `8 test files, 823 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-duplicate-filter-boundary-currentbase.php
```

Passed: emitted `duplicate_filter_declarations_rejected=true`, `filters_resolved=false`, `review_filters=["FlateDecode","DCTDecode"]`, `preview_only_filters=["DCTDecode"]`, `dctdecode_filter_review_only=true`, `renderer_duplicate_filter_review_only=true`, `payload_excluded_from_paragraphs=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- PHP behavior tests move `2041 -> 2043`.
- `phpPass` moves `2263 -> 2265`.
- WordPress scenarios move `1946 -> 1947`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, duplicate top-level name scanner, Image XObject review pipeline, existing DCTDecode preview-only filter classification, and native stream filter decoder fail-closed boundary. Live OCR, Surya/Texify/Torch model execution, pypdfium/PIL rasterization, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF boundary.

## Non-Overlap

This does not repeat stale `/Length` DCT stream recovery, DCT JPEG segment/SOS marker boundaries, RunLength/LZW/ASCII85/ASCIIHex prefix-filter DCT EOD guards, escaped DCT filter names, inline image DCT handling, DCT alternate images, masks, CCITTFax review, JPX soft-mask behavior, or metadata/XMP parser work. The bounded new behavior is duplicate top-level Image XObject `/Filter` declarations that include a DCTDecode filter.
