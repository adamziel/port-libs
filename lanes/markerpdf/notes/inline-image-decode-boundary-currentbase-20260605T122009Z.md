# markerPDF inline image DecodeParms boundary current-base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T122009Z`
Base: `2eb3d4038b9e93816e26565fe8d737d48cc80c63`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction and image rendering boundaries to parser/PDFium-backed PDF content handling before any OCR/model fallback. Under the current no-GPU markerPDF scope, the PHP lane owns the native parser equivalent for inline image `BI`/`ID`/`EI` tokenization, stream-filter metadata, and WordPress paragraph safety without running PDFium, PIL, Python models, OCR, or external PDF tools.

PDF inline images can declare native filters such as `FlateDecode` with `/DecodeParms`. If those DecodeParms are malformed or unapplicable, the filter stack is not safely decodable. The parser should keep the payload closed as image data, preserve following visible text, and mark the preview as fail-closed review metadata rather than claiming native raster decode.

## Behavior

`PdfTextExtractor` now treats tokenizer-boundary inline image filters with malformed or invalid DecodeParms as unsupported preview-style boundaries. This prevents a bad native filter declaration such as `FlateDecode` with `/Predictor 12 /Columns 0` from swallowing the text object after the image payload.

`PdfImageRenderer` now applies the same fail-closed classification in `inlineImageReviewPlan()`: native inline image filters remain native only when their DecodeParms are absent/null or valid for that filter. Invalid DecodeParms set `unsupported_filters`, clear `native_raster_decode`, and keep the image review-only until a safe raster backend exists.

The WordPress smoke now covers malformed filter operands, invalid native DecodeParms, and unresolved filter operands in one inline-image fixture. All image payload text stays out of Gutenberg paragraphs and no model/external-tool path is executed.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 348 assertions, 1 failures
```

Focused green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 365 assertions, 0 failures
```

Adjacent inline renderer family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 968 assertions, 0 failures
```

Adjacent text/image-filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 1393 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-malformed-filter-preview-currentbase.php
invalid_decodeparms_inline_filter_preview_failed_closed=true
invalid_decodeparms_inline_filter_unsupported=[FlateDecode]
invalid_decodeparms_native_raster_decode=false
inline_payload_excluded_from_text=true
executes_python_or_models=false
executes_external_pdf_tools=false
executes_pypdfium_or_pil=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CMap explicit filter terminator boundaries, inline image filter EOD surplus handling, Flate post-stream surplus closure, LZW/RunLength EOD checks, inline ImageMask sample decoding, inline Indexed palette/soft-mask previews, DCT/CCITT/JPX/JBIG2 review-only image filters, xref repair, metadata extraction, annotations, forms, page geometry, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded behavior here is invalid native inline image DecodeParms fail-closed classification shared between text tokenization and image preview metadata.

## Dependency Closure

No new support component is needed. This slice reuses native PHP content-stream tokenization, stream filter parsing, DecodeParms validation, inline image preview metadata, text extraction, and the existing WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL rasterization, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
