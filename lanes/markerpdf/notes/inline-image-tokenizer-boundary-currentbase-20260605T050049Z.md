# markerPDF Inline Image Tokenizer Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T050049Z`

Base accepted HEAD: `11a7b6924e8b549c836158a54da8e2a995e7ea6f`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline-image bytes are raster payload, while text arrays after the real inline-image terminator remain visible document text.

This current-base slice fixes the already-present TJ-array boundary on the accepted base: preview-only inline-image fallback must close before resumed `TJ` array text whose literal contains standalone `EI` bytes.

## Red First

Before the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

failed `closes preview-only inline image fallback before TJ array text containing EI bytes`. The extractor returned only `['Before TJ Array EI Text']` instead of `['Before TJ Array EI Text', 'Visible EI Array Text']`, and emitted repeated `Undefined variable $insideTextObject` warnings from the inline-image fallback scanner.

## Implementation

`PdfTextExtractor::contentSegmentContainsUnterminatedTextLiteralAfterTextObject()` now scans arrays while inside resumed text objects and reports unterminated array literals before the next raw `EI` candidate. That lets `skipInlineImage()` close at the safe preview-only fallback terminator instead of swallowing visible `TJ` text.

The stale array check was removed from `contentSegmentContainsInlineImagePreamble()`, where no text-object state is tracked and where it caused the undefined variable warning.

## Verification

Focused tokenizer run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 144 assertions, 0 failures
```

Adjacent inline-image/text sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1670 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke renders `Visible EI Array Text`, excludes `TJ Array Payload EI Noise`, and emits `preview_only_visible_ei_tj_array_text_preserved_after_safe_boundary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered sample-length `EI` validation, slash-delimited inline dictionaries, nested modifier-dictionary decoys, ASCII85/Flate/LZW/RunLength DecodeParms validation, DCT/JPX/JBIG2/CCITT preview-only framing, wrapped preview-filter chains, raw JBIG2 segments, unsupported `/Crypt` filter boundaries, named ColorSpace fallback, plain visible `EI` literal recovery, slash-delimited `EI/name` closure, object-stream inline-image repair, image XObject payload exclusion, or stream-owner `endstream` decoy recovery.

The bounded behavior is specifically resumed `TJ` text-array literals containing standalone `EI` bytes after a preview-only inline-image fallback boundary.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, PDF array/literal tokenizer, preview-only filter boundaries, `PdfTextExtractor`, focused lane tests, and the WordPress smoke path. Full upstream parity remains intentionally gated on live pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
