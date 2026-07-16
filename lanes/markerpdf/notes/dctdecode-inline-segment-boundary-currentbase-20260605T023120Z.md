# markerPDF Inline DCTDecode Segment Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T023120Z`

Base accepted HEAD: `9690ec9e5d91db42a252ae8d5492cd60965d3988`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`; raster JPEG bytes remain image payloads handled by image rendering paths, not text extraction. For the native PHP no-GPU boundary, inline image `/DCTDecode` payloads must therefore stay closed until a real JPEG EOI marker, not a byte pair inside a length-coded APP segment followed by a fake `EI` token.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps inline DCTDecode image payloads inside JPEG EOI boundaries before WordPress text extraction
FAIL ignores false inline DCTDecode EOI markers inside length-coded APP segments (lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before inline APP JPEG',
  1 => 'After inline APP JPEG',
)
Actual: array (
  0 => 'Before inline APP JPEG',
  1 => 'Inline APP DCT segment leak',
  2 => 'After inline APP JPEG',
)

1 test files, 9 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::inlineDctCandidateState()` now reuses the existing segment-aware JPEG completion walker instead of accepting any raw `FF D9` byte pair. The shared walker skips length-coded JPEG segments before accepting EOI, so a fake `EI` after an APP-segment false EOI no longer reopens WordPress text-token parsing.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps inline DCTDecode image payloads inside JPEG EOI boundaries before WordPress text extraction
PASS ignores false inline DCTDecode EOI markers inside length-coded APP segments

1 test files, 15 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 73 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-dctdecode-segment-boundary-currentbase.php
```

The smoke emits `fake_inline_ei_after_false_eoi_ignored=true`, `inline_dctdecode_payload_excluded_from_text=true`, paragraphs `["Before Inline APP DCT Import","After Inline APP DCT Import"]`, and all Python/model, pypdfium/PIL, and external PDF tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream DCTDecode raw JPEG SOI/EOI recovery, Flate-prefix DCT stream recovery, DCT CMYK/YCCK Decode review, generic image filter review-only metadata, CCITT/JPX/JBIG2 image-filter exclusion, or inline DCT EOI handling for ordinary payloads. This bounded slice covers inline-image tokenizer behavior where a false JPEG EOI appears inside a length-coded APP segment before a fake `EI` token.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF content tokenizer, inline image parser, stream filter metadata parser, and existing segment-aware DCT preview boundary. Full JPEG raster parity remains gated on a future native raster backend or pypdfium2/PDFium path; OCR/model execution remains intentionally out of scope under the current markerPDF no-GPU directive.
