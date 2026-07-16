# Inline DCTDecode Padded SOI Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T053524Z`
Base accepted HEAD: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`

## Behavior

Upstream markerPDF routes page text extraction through the PDF parser text
boundary while inline image payloads stay image data. In the no-GPU PHP lane,
DCTDecode JPEG bytes remain review-only, but the tokenizer must still use JPEG
SOI/EOI framing to reject delimiter-looking `EI` bytes inside inline images.

The existing Image XObject DCT boundary already accepted leading NUL padding,
UTF-8 BOM bytes, and JPEG marker-fill bytes before SOI. Inline DCT detection
used a stricter exact `FF D8` prefix check, so padded inline JPEG payloads could
be classified as unknown at an early `EI` decoy and leak following fake text.

`PdfTextExtractor::inlineDctCandidateState()` now reuses the same
`dctPreviewSoiOffset()` scanner as XObject DCT streams before deciding whether
an inline DCT payload is unknown, incomplete, or complete.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodePaddedSoiBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps padded inline DCTDecode SOI payloads closed until real JPEG EOI before WordPress text extraction
Expected: ["Before NUL padded inline DCT","After NUL padded inline DCT"]
Actual: ["Before NUL padded inline DCT","NUL padded inline DCT leak","After NUL padded inline DCT"]
1 test files, 1 assertions, 1 failures
```

Passing focused regression after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodePaddedSoiBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps padded inline DCTDecode SOI payloads closed until real JPEG EOI before WordPress text extraction
1 test files, 32 assertions, 0 failures
```

Adjacent inline DCT/tokenizer run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodePaddedSoiBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 806 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-dctdecode-padded-soi-boundary-currentbase.php
```

Result: emitted clean paragraphs for NUL-padded and BOM/marker-fill inline DCT
imports, `payload_excluded_from_text=true`, `review_only_filters=["DCTDecode"]`,
`native_raster_decode=false`, and no Python/model/PDFium/PIL/external-tool
execution.

## Dependency Closure

No new support component is needed. This reuses the native PDF content tokenizer,
inline image dictionary expander, DCT/JPEG SOI scanner, and WordPress paragraph
handoff. Full JPEG raster parity remains gated on pypdfium/PDFium/PIL or a
future native raster backend, which remains outside this no-GPU slice.

## Non-Overlap

This does not repeat accepted inline DCT exact-SOI EOI validation, wrapped
ASCIIHex DCT inline boundaries, XObject DCT BOM/marker-fill boundaries, false
EOI recovery, DCT DecodeParms metadata, malformed filter operands, CCITT/JPX/
JBIG2 preview filters, or live OCR/model/raster execution. The owned behavior is
only padded inline DCT SOI detection before tokenizer `EI` decoys.
