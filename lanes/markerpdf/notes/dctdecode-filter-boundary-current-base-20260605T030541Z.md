# markerPDF DCTDecode ASCIIHex EOD Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T030541Z`

Base accepted HEAD: `7e8350b1ef3db6f47e1658b3972bdea83e44a6f0`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed PDF text extraction before image rendering, OCR, and model stages. DCTDecode JPEG bytes are image payloads handled by `marker.pdf.images.render_image` and must not become WordPress paragraph text.

For this native no-GPU PHP boundary, a PDF stream with `/Filter [/ASCIIHexDecode /DCTDecode]` must not accept a false ASCIIHex `>` followed by fake `endstream/endobj` tokens unless the bytes decoded before `/DCTDecode` are a complete JPEG. If the early prefix-filter EOD is malformed or incomplete for the DCT image, the stream owner must stay closed so fake object text inside the image payload is not scanned as a direct PDF object.

## Red First

An ad hoc PHP fixture on the accepted base built an image stream with:

- `/Filter [/ASCIIHexDecode /DCTDecode]`;
- stale `/Length` or missing length pointing to a false ASCIIHex `>` followed by fake `endstream/endobj`;
- a fake `9 0 obj` containing `ASCIIHex DCT early EOD leak`;
- a later review boundary for the full image payload.

Before the patch, `PdfTextExtractor::extractTextLines()` returned:

```text
Before ASCIIHex DCT stream
ASCIIHex DCT early EOD leak
After ASCIIHex DCT stream
```

## Implementation

`PdfTextExtractor::dctPrefixFilterEndstreamTerminatorOffset()` now validates EOD-backed prefix-filter candidates by decoding only the filters before `/DCTDecode` and requiring the resulting bytes to be a complete JPEG before accepting that `endstream` boundary.

For malformed early-EOD candidates, the DCT prefix recovery keeps scanning marker-backed terminators and fails closed to the latest explicit prefix-filter terminator, extending the image stream owner range instead of letting fake object tokens reopen fallback text extraction.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 100 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 1547 assertions, 0 failures
```

Syntax checks passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-asciihex-eod-boundary-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-asciihex-eod-boundary-currentbase.php
```

The smoke emits `fake_asciihex_eod_endstream_ignored=true`, `dctdecode_image_payload_excluded_from_text=true`, paragraphs `["Before ASCIIHex DCT Import","After ASCIIHex DCT Import"]`, and all Python/model, pypdfium/PIL, and external PDF tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCT SOI/EOI stream recovery, DCT APP-segment false-EOI handling, Flate-wrapped DCT recovery, inline DCT tokenizer framing, DCT CMYK/YCCK Decode review, CCITT/JPX/JBIG2 image-filter exclusion, or generic compact DecodeParms/null-filter stack behavior.

The bounded new behavior is specifically prefix-filtered `/ASCIIHexDecode` before `/DCTDecode` where a false early ASCIIHex EOD plus fake stream/object tokens must not truncate the image stream owner range before WordPress text extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream-filter parser, ASCIIHex decoder, segment-aware DCT/JPEG boundary checker, `PdfTextExtractor`, and existing image XObject review metadata. Full live JPEG raster parity remains gated on a future native raster backend or the pypdfium2/PDFium path; OCR/model execution remains intentionally out of scope under the current markerPDF no-GPU directive.
