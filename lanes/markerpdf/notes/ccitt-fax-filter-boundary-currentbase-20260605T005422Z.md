# markerPDF CCITT Fax Filter Boundary Current Base

Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T005422Z`

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T005422Z`

Base accepted HEAD: `c39e6ef5dc53ab6c10abe3cd85218cbaaa83096e`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with image pixels handed to `marker/pdf/images.py::render_image()` before model/OCR stages. Under the current no-GPU scope, native PHP keeps CCITTFaxDecode/CCF raster payloads review-only while preserving filter and DecodeParms metadata for WordPress import review.

PDF stream filter arrays are ordered. A stream with native prefix filters followed by CCITTFaxDecode must not accept a fake line-start `endstream` token inside the encoded prefix-filter bytes just because the final CCITT raster filter is preview-only.

## Behavior

`PdfTextExtractor` now repairs CCITT preview-only image stream boundaries after native prefix filters. For `/Filter [/FlateDecode /CCITTFaxDecode]`, missing or stale `/Length` streams decode the Flate prefix up to the CCITT stage before accepting the real stream terminator. The parser still does not rasterize CCITT fax bytes, and direct CCITT streams without a native prefix remain fail-closed to the existing conservative boundary behavior.

The focused fixture embeds a raw `endstream/endobj` fake object sequence inside the Flate stored block before the real image stream terminator. Before the patch, WordPress paragraph extraction leaked `Fake Flate CCITT prefix leak` and image review saw the stream truncated to 11 bytes. After the patch, visible text contains only the page paragraphs and image review preserves the full compressed stream length.

## Evidence

Red-first focused run before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults
PASS marks inline CCITT Fax image filters review-only before WordPress image preview
PASS marks malformed inline CCITT Fax DecodeParms fail closed before RGB preview
FAIL keeps Flate-wrapped CCITT Fax endstream decoys inside image payload boundaries (lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 145
Actual: 11

1 test files, 90 assertions, 1 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults
PASS marks inline CCITT Fax image filters review-only before WordPress image preview
PASS marks malformed inline CCITT Fax DecodeParms fail closed before RGB preview
PASS keeps Flate-wrapped CCITT Fax endstream decoys inside image payload boundaries

1 test files, 100 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `flate_wrapped_ccitt_stale_length_repaired=true`, `flate_wrapped_ccitt_payload_excluded=true`, `inline_invalid_decode_parms_valid=false`, `image_only_filter_skipped=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, while rendering only `CCITT Boundary` and `Native Import` paragraphs.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CCITT DecodeParms metadata, inline CCITT invalid DecodeParms fail-closed review, DCT/JPEG prefix boundary repair, generic ASCII85/Flate content-stream stack recovery, stream-filter DecodeParms fail-closed behavior, Image XObject review-only metadata, inline image tokenizer boundaries, or CCITT direct-raster decoding. The added behavior is specifically native-prefix stream-boundary recovery before a preview-only CCITT final filter.

## Status Delta

- Focused PASS cases: `1210 -> 1211`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `82 -> 100`.
- WordPress scenarios: `1189 -> 1190`.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, stream dictionary reader, filter resolver, Flate/LZW/ASCIIHex/ASCII85/RunLength prefix decoders, CCITT review-only image filter metadata, content text extractor, and WordPress smoke renderer. Full CCITT raster decoding, pypdfium/PIL pixel rendering, live OCR, Surya/Torch model execution, Texify, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
