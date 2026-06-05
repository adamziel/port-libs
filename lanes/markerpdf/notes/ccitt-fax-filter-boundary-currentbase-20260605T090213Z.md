# markerPDF CCITT Fax Alias Filter Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T090213Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T090213Z`
Base accepted HEAD: `f0deb377edb0acf5cab465b164fb1c6dc0b4c874`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text extraction separate from image rendering. CCITT Fax image bytes remain raster/image-review payloads, not WordPress paragraph text. In the native no-GPU PHP lane, `/CCITTFaxDecode` and accepted `/CCF` alias stacks stay review-only while still exposing enough filter metadata for media-review handoff.

The bounded PDF parser behavior here is filter identity rather than raster decoding: a declared `/CCF` filter must remain visible as the source filter, while review consumers also need a stable canonical `CCITTFaxDecode` identity and native prefix-filter context.

## Native Behavior Added

`PdfImageRenderer` and `PdfTextExtractor` now emit additive `ccitt_fax_filter_boundary` metadata for the first CCITT Fax filter in an image stack:

- `declared_filter` preserves the original filter name, including `CCF`;
- `canonical_filter` is always `CCITTFaxDecode`;
- `alias_used` marks declared `CCF` aliases;
- `filters_before_ccitt` and `native_prefix_filters` preserve prefix decoders such as `ASCIIHexDecode`;
- `review_only=true` and `native_raster_decode=false` remain explicit.

Existing public `filters` and `filter_details` values are unchanged, so this is additive metadata for WordPress review and future raster handoff.

## Evidence

Focused CCITT gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS preserves declared CCF aliases while exposing canonical CCITT filter metadata

1 test files, 301 assertions, 0 failures
```

Adjacent image/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1979 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `xobject_compact_declared_alias_preserved=true`, `xobject_compact_native_prefix_filters=["ASCIIHexDecode"]`, `xobject_compact_payload_excluded_from_review=true`, `xobject_compact_payload_excluded_from_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1652 -> 1653`.
- Focused CCITT assertions: `278 -> 301`.
- WordPress scenarios: `1519 -> 1520`.
- Mapped `pdfCcittFaxFilterBoundaryCurrentBaseBehaviors`: `1 -> 2`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, invalid/unresolved DecodeParms fail-closed metadata, null-filter DecodeParms alignment, compact DecodeParms arrays, escaped DecodeParms names, Flate/Crypt prefix recovery, direct EOFB/RTC stream ownership, coding-mode metadata, ImageMask polarity review, nested SMask/Mask/Alternate CCITT rows, DCT/JPX/JBIG2 preview-only filters, or generic inline-image payload exclusion. The new behavior is only additive declared-alias plus canonical CCITT filter identity metadata for image review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, stream-filter resolver, image filter metadata planner, Image XObject review path, and existing WordPress smoke. Full CCITT raster decoding remains out of scope for this no-GPU slice and would require a future native raster backend or PDFium/PIL-equivalent support with separate fixtures before activation.
