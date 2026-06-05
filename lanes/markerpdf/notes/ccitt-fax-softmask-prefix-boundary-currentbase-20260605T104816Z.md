# CCITT Fax Soft-Mask Native-Prefix Boundary - Current Base

Date: 2026-06-05 UTC

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T104816Z`

Base accepted HEAD: `3e6043929d9e6e5fc600c50f3b34e370b206774e`

## Source Truth

Upstream markerPDF routes searchable PDF text through PDF parser/text extraction and sends image streams through image rendering before RGB handoff. Under the native no-GPU markerPDF scope, CCITTFaxDecode and CCF raster data remain review-only, but native prefix filters such as ASCIIHexDecode can still be decoded safely before the preview-only fax stage.

This slice keeps the fax raster boundary closed while preserving soft-mask stream prefix metadata for WordPress media review:

- `/SMask` streams with `[/ASCIIHexDecode /CCF]` remain preview-only and do not claim full raster decode.
- `soft_mask_filter_boundary` now records `native_prefix_decoded`, length, SHA-256, preview hex, and `stopped_before_filter`.
- The decoded fax-prefix bytes are represented only as bounded metadata; stream payload bytes are not promoted into visible text or review JSON.

## Red-First Evidence

Before the source change, the focused current-base test failed because the soft-mask boundary lacked `native_prefix_decoded`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records native prefix decoded bytes before CCITT Fax soft-mask review handoff
1 test files, 337 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 344 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageDeviceNSeparationSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNTransferJpxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRenderingColorSpaceSoftMaskTransferBundleCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1005 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
emits soft_mask_prefix_filters=["ASCIIHexDecode","CCF"], soft_mask_prefix_native_decoded=true, soft_mask_prefix_native_decoded_length=3, soft_mask_prefix_stopped_before_filter="CCF", soft_mask_prefix_payload_excluded_from_review=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, DecodeParms extraction/fail-closed handling, escaped filters, null-filter DecodeParms alignment, Flate/Crypt prefix stream-owner repair, direct EOFB/RTC ownership, coding-mode metadata, CCF alias preservation, post-CCITT filter reachability, primary/inline/nested ImageMask polarity, or the generic inline JPX native-prefix preview slice. The bounded behavior is specifically renderer soft-mask filter-boundary metadata for native prefix bytes decoded before a preview-only CCITT Fax handoff.

## Dependency Closure

No new support component is needed. This reuses the native PHP image stream filter decoder, soft-mask review boundary, CCITT preview-only filter metadata, and WordPress smoke path. Full CCITT raster decoding remains intentionally out of scope for the no-GPU markerPDF lane and would require a future native raster backend or explicitly authorized PDFium/PIL-style support.
