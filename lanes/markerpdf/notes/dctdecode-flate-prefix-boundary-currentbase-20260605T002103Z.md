# markerPDF DCTDecode Flate-Prefix Stream Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T002103Z`

Base accepted HEAD: `9b1ef263ff3924c6fe0e7eac819c5983af847fea`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and renders raster images through `marker/pdf/images.py`. A stream filtered as `/FlateDecode` then `/DCTDecode` is still an image payload at this boundary; text-looking bytes inside the decoded JPEG must not become WordPress paragraphs.

This native no-GPU slice maps the parser boundary for DCTDecode streams that have verifiable prefix filters. The parser now decodes filters before the DCT step, verifies complete JPEG SOI/EOI framing, and only then accepts a later `endstream` terminator when an early raw fake terminator appears inside a Flate stored block.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS marks DCTDecode image filters review-only before RGB preview metadata
PASS keeps DCT alias inline image review metadata out of native raster decode
PASS records DCTDecode ColorTransform DecodeParms on image XObject review rows
PASS keeps DCTDecode JPEG endstream decoys inside image payload boundaries
FAIL keeps Flate-wrapped DCTDecode JPEG endstream decoys inside image payload boundaries (lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before Flate DCT stream',
  1 => 'After Flate DCT stream',
)
Actual: array (
  0 => 'Before Flate DCT stream',
  1 => 'Fake Flate DCT prefix leak',
  2 => 'After Flate DCT stream',
)

1 test files, 36 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::dctStreamEndstreamTerminatorOffset()` now handles DCTDecode behind prefix filters such as `/FlateDecode` by decoding only the filter stack before the DCT step and checking for a complete JPEG preview boundary. Existing ASCIIHex/ASCII85/RunLength explicit EOD behavior is preserved for non-JPEG preview-only streams.

The focused test covers both missing `/Length` and stale `/Length` pointing at a raw fake `endstream` inside the Flate stored block.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 45 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1478 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-flate-prefix-boundary-currentbase.php
```

The smoke emits `stream_filters=["FlateDecode","DCTDecode"]`, `prefix_filter_decoded_before_dct_boundary=true`, `missing_length_fake_endstream_rejected=true`, `stale_length_fake_endstream_rejected=true`, `dctdecode_image_payload_excluded_from_text=true`, paragraphs `["Before Flate DCT Import","After Flate DCT Import"]`, and all Python/model, pypdfium/PIL, and external PDF tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCTDecode raw JPEG SOI/EOI stream recovery, ASCII85/ASCIIHex/RunLength prefix EOD recovery, inline DCTDecode tokenizer boundaries, DCT CMYK Decode/ColorTransform review, generic image filter review-only metadata, CCITT/JPX/JBIG2 image-filter exclusion, or broad stream-filter stack recovery. The bounded behavior is specifically Flate/LZW-style prefix-filter recovery before the preview-only DCTDecode step.

## Dependency Closure

No new support component is needed. This reuses the native PHP stream filter decoders, PDF dictionary/value parser, DCT preview-only boundary, and WordPress smoke path. Full raster parity remains gated on pypdfium2/PDFium or a future native JPEG raster backend; OCR/model execution remains intentionally out of scope under the current markerPDF no-GPU directive.
