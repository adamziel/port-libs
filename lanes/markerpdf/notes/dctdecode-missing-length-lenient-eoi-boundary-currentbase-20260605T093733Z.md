# markerPDF DCTDecode Missing-Length Lenient EOI Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T093733Z`

Base accepted HEAD: `56b931df2c191390c2ffd199ea6032951839d3df`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and routes raster image rendering through `marker/pdf/images.py`. A `/DCTDecode` image stream is image payload at that parser boundary, so text-looking bytes inside malformed JPEG data must not become WordPress paragraph text.

This no-GPU native PHP slice covers raw DCTDecode image streams that omit `/Length`. A malformed JPEG can contain an early false `FF D9` followed by fake `endstream`/object bytes before the real final `FF D9`. The native parser now chooses the last complete raw DCTDecode EOI/endstream candidate in that stream preview instead of stopping at the first false complete-looking boundary.

## Red First

After adding the missing-Length malformed DCTDecode fixture on the accepted base, the focused file failed by leaking fake object text from inside the JPEG payload:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps missing-Length malformed DCTDecode false EOI decoys before final JPEG boundary
Expected: ['Before missing length malformed DCT','After missing length malformed DCT']
Actual: ['Before missing length malformed DCT','Missing length malformed DCT leak','After missing length malformed DCT']

1 test files, 315 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::rawDctPreviewEndstreamTerminatorOffset()` now scans all raw JPEG preview EOI candidates and returns the last candidate that is followed by an `endstream` keyword. This keeps malformed missing-Length DCTDecode payload bytes closed across false EOI/fake terminator decoys while preserving stale-Length minimum-boundary behavior.

Prefix-filtered DCTDecode streams are intentionally not changed by this patch. That path decodes prefix filters first and has separate accepted Flate/LZW-style preview semantics.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 338 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1372 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-lenient-eoi-boundary-currentbase.php
```

The smoke exits 0 and emits `false_eoi_before_fake_endstream_ignored=true`, `missing_length_false_eoi_before_fake_endstream_ignored=true`, `raw_length_after_boundary_recovery=208`, `missing_length_raw_length_after_boundary_recovery=208`, `dctdecode_image_payload_excluded_from_text=true`, and all Python/model, pypdfium/PIL, and external PDF tool execution flags false.

Root harness: not run - isolated micro-slice.

## Exclusions

The broader command including `lanes/markerpdf/tests/PdfTextExtractorTest.php` still has two unrelated accepted-base failures around unsupported stream-filter payload leakage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 617 assertions, 2 failures
```

That file failed the same way after temporarily reverting this raw-DCT helper change, so the unsupported-filter failures are not introduced by this slice.

## Non-Overlap

This does not repeat accepted direct stale-Length DCTDecode recovery, Flate/LZW prefix-filter DCTDecode boundary recovery, Crypt Identity DCTDecode boundary recovery, inline DCTDecode tokenizer boundaries, DCT CMYK Decode/ColorTransform review, generic image filter review-only metadata, CCITT/JPX/JBIG2 image-filter exclusion, or broad stream-filter stack recovery. The bounded behavior is specifically raw missing-Length malformed DCTDecode streams with false complete-looking EOI/endstream decoys before the final JPEG boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream dictionary parser, DCT preview-only boundary scanner, image XObject review metadata, and WordPress smoke path. Full raster parity remains gated on pypdfium2/PDFium/PIL or a future native JPEG raster backend; OCR/model execution remains intentionally out of scope under the current markerPDF no-GPU directive.
