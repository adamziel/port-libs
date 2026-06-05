# markerPDF DCTDecode Stream Terminator Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260604T234541Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260604T234541Z`
Base accepted HEAD: `57058b982e38efb74137da09319fa7203abc89a4`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` with `pdftext.extraction.dictionary_output(...)`, while page/image rendering goes through PDFium/PIL in `marker/pdf/images.py`. Under the current no-GPU/no-model lane scope, the PHP port does not execute PDFium, PIL, OCR, Torch, or model workers.

DCTDecode streams are JPEG image payloads, not text streams. The native parser still does not decode JPEG pixels, but JPEG SOI/EOI framing is enough to reject fake `endstream/endobj` and `obj` tokens inside image bytes before fallback WordPress text extraction.

## Native Behavior Added

`PdfTextExtractor` now uses a DCT/JPEG stream terminator boundary for `/DCTDecode` and `/DCT` streams:

- direct object scanning skips through JPEG EOI before accepting `endstream`;
- stale `/Length` values that point at fake `endstream` bytes inside JPEG payloads are repaired to the real EOI-adjacent terminator;
- missing `/Length` DCT streams use the same EOI boundary;
- explicit prefix filters with native EOD markers (`ASCIIHexDecode`, `ASCII85Decode`, and `RunLengthDecode`) can bound wrapped DCT streams before fake `endstream` bytes;
- fake indirect objects embedded before JPEG EOI are not promoted into fallback decoded streams.

This remains parser-only. It does not add JPEG raster decoding or image pixel conversion.

## Red-First Evidence

Before the source change, after adding the focused assertions:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS marks DCTDecode image filters review-only before RGB preview metadata
PASS keeps DCT alias inline image review metadata out of native raster decode
PASS records DCTDecode ColorTransform DecodeParms on image XObject review rows
FAIL keeps DCTDecode JPEG endstream decoys inside image payload boundaries (lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Before DCT stream boundary',
  1 => 'After DCT stream boundary',
)
Actual: array (
  0 => 'Before DCT stream boundary',
  1 => 'Fake DCT stream object leak',
  2 => 'After DCT stream boundary',
)

1 test files, 21 assertions, 1 failures
```

## Verification

Focused DCT file:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php && php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
Focused test run: 1 selected test files (root lock skipped)
PASS marks DCTDecode image filters review-only before RGB preview metadata
PASS keeps DCT alias inline image review metadata out of native raster decode
PASS records DCTDecode ColorTransform DecodeParms on image XObject review rows
PASS keeps DCTDecode JPEG endstream decoys inside image payload boundaries

1 test files, 35 assertions, 0 failures
```

Focused parser/image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 937 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-stream-terminator-boundary-currentbase.php
```

The smoke emits `jpeg_soi_eoi_delimiter_guard=true`, `prefix_filter_eod_guard=true`, `stale_length_fake_endstream_rejected=true`, `embedded_fake_object_rejected=true`, renders only `Before DCT Stream` and `After DCT Stream`, and records all Python/model/PDFium/PIL/external-tool execution flags as false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCT preview-only filter classification, DCT `/DecodeParms /ColorTransform` metadata, DCT CMYK/YCCK `/Decode` RGB preview planning, inline DCT `EI` delimiter scanning, generic stream filter-stack recovery, stream-owned xref-object rejection, image XObject payload exclusion, JPX/JBIG2/CCITT image-filter boundaries, or stale stream `/Length` recovery for native-decodable filters.

The new behavior is specifically DCTDecode JPEG SOI/EOI ownership, plus explicit prefix-filter EOD ownership before DCT, for direct PDF stream terminators before fallback text/object scanning.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF direct-object scanner, stream dictionary parser, filter-name resolver, fallback stream extractor, content text extraction path, and WordPress smoke renderer. Full JPEG raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; no Python, OCR, models, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools were executed.
