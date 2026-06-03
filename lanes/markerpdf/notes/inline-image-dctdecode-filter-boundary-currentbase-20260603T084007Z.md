# markerPDF Inline DCTDecode Filter Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260603T084007Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260603T084007Z`
Base accepted HEAD: `72f5cb84857abafdc63cdb83c5e14ce84d9bf3fb`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text extraction through `marker/pdf/extract_text.py` and pdftext/PDFium parsing before WordPress block rendering. At this boundary, inline image payload bytes are image data and must not be tokenized as text operators.

DCTDecode inline images are JPEG payloads. The native PHP parser still does not rasterize JPEGs, but JPEG SOI/EOI framing is enough to reject delimiter-looking `EI` bytes before the JPEG EOI marker.

## Native Behavior Added

`PdfTextExtractor::skipInlineImage()` now treats `/DCTDecode` and `/DCT` candidates that begin with JPEG SOI as incomplete until an EOI marker is present. When supported prefix filters such as `/ASCIIHexDecode` wrap the JPEG bytes before `/DCTDecode`, the native parser decodes only those prefix filters, checks JPEG framing, and stops at the DCT preview-only boundary. This prevents text-looking bytes after a fake inline-image `EI` marker inside JPEG data from becoming visible WordPress paragraphs, while preserving later page content after wrapped DCT images. Truncated DCT payloads still use the existing preview-only fallback boundary instead of consuming the rest of the content stream indefinitely.

## Evidence

Red-first focused failure before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps inline DCTDecode image payloads inside JPEG EOI boundaries before WordPress text extraction
1 test files, 1 assertions, 1 failures
```

Passing focused and regression gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 642 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-dctdecode-filter-boundary-currentbase.php
```

The smoke emitted two Gutenberg paragraphs, `Inline DCT Import` and `Clean WordPress Paragraph`, with `jpeg_eoi_delimiter_guard=true`, `excluded_inline_jpeg_noise=true`, and all Python/model/PDFium/external-tool execution flags false.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-dctdecode-filter-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCTDecode image XObject text exclusion, DCT CMYK/YCCK color-transform planning, DCT `/Decode` sample review, JPX inline EOC delimiter handling, inline Flate/LZW DecodeParms validation, inline filter-array abbreviation/null-entry handling, or generic stream-filter fail-closed behavior. The new behavior is specifically inline DCTDecode `/DCT` JPEG EOI delimiter validation, including supported prefix-filter unwrapping before the DCT preview boundary, before content-stream text tokenization.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF content tokenizer, inline image dictionary expander, stream-filter name parser, and WordPress paragraph extraction path. Live JPEG raster parity remains gated on pypdfium/PIL/PDFium or a future native raster backend; no Python, models, OCR, pypdfium, PIL, Poppler, Ghostscript, or external PDF tools were executed.
